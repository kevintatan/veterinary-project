<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Documents\RemoteSigning;
use App\Actions\Documents\SignPetDocument;
use App\Auth\Permission;
use App\Documents\DocumentPdf;
use App\Documents\DocumentRenderer;
use App\Documents\DocumentTypes;
use App\Http\Controllers\Controller;
use App\Models\Clinic;
use App\Models\ClinicMembership;
use App\Models\Documents\PetDocument;
use App\Models\Documents\PetDocumentRequest;
use App\Models\Documents\PetDocumentSignature;
use App\Models\Pet;
use App\Models\User;
use App\Support\ClinicTime;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Consents and authorizations signed on screen (Documentos y firmas, D1–D2).
 * docs/documentos.md
 */
class PetDocumentController extends Controller
{
    public function __construct(private readonly DocumentRenderer $renderer) {}

    /** The templates: what each one asks for and who signs it. */
    public function types(): JsonResponse
    {
        return response()->json(['data' => collect(DocumentTypes::all())->map(fn ($t, $code) => [
            'code' => $code,
            'name' => $t['name'],
            'short' => $t['short'],
            'description' => $t['description'],
            'fields' => $t['fields'],
            'signers' => collect($t['signers'])->map(fn ($s, $role) => ['role' => $role, ...$s])->values(),
            'allows_witness' => isset($t['signers']['witness']),
        ])->values()]);
    }

    public function index(Request $request, Pet $pet): JsonResponse
    {
        $docs = PetDocument::with(['signatures', 'vet:id,name'])->where('pet_id', $pet->id)->latest()->get();

        return response()->json(['data' => $docs->map(fn (PetDocument $d) => $this->summary($d, $this->clinic($request)))]);
    }

    public function store(Request $request, Pet $pet): JsonResponse
    {
        $data = $this->validated($request);
        $clinic = $this->clinic($request);
        $vet = $this->vet($data['vet_id'] ?? $request->user()->id);

        $snapshot = $this->snapshotFor($data, $clinic, $pet, $vet);

        $doc = PetDocument::create([
            'pet_id' => $pet->id,
            'owner_id' => $pet->owner_id,
            'type' => $data['type'],
            'template_version' => DocumentTypes::get($data['type'])['version'],
            'title' => $snapshot['title'],
            'data' => $data['data'] ?? [],
            'snapshot' => $snapshot,
            'vet_id' => $vet->id,
            'created_by' => $request->user()->id,
        ]);

        return response()->json(['data' => $this->detail($doc, $request)], 201);
    }

    public function show(Request $request, PetDocument $petDocument): JsonResponse
    {
        return response()->json(['data' => $this->detail($petDocument, $request)]);
    }

    /** Only a draft (nobody signed yet) can change. */
    public function update(Request $request, PetDocument $petDocument): JsonResponse
    {
        if (! $petDocument->isEditable()) {
            throw ValidationException::withMessages(['type' => 'El documento ya tiene firmas: no se puede modificar. Anúlalo y crea uno nuevo si hay que corregir algo.']);
        }

        $request->merge(['type' => $petDocument->type]);
        $data = $this->validated($request);
        $vet = $this->vet($data['vet_id'] ?? $petDocument->vet_id);
        $snapshot = $this->snapshotFor($data, $this->clinic($request), $petDocument->pet, $vet);

        $petDocument->update(['data' => $data['data'] ?? [], 'snapshot' => $snapshot, 'vet_id' => $vet->id, 'title' => $snapshot['title']]);

        return response()->json(['data' => $this->detail($petDocument->fresh(), $request)]);
    }

    public function sign(Request $request, PetDocument $petDocument, SignPetDocument $sign): JsonResponse
    {
        $input = $request->validate([
            'role' => ['required', Rule::in(['owner', 'vet', 'witness'])],
            'image' => ['required', 'string', 'max:1000000'],
            'accepted' => ['accepted'],
            'signer_name' => ['nullable', 'string', 'max:150'],
            'signer_document' => ['nullable', 'string', 'max:40'],
        ], ['accepted.accepted' => 'Hay que confirmar que se leyó y se acepta el documento.'], ['image' => 'firma']);

        $doc = $sign($petDocument, $input, $request->user(), $request);

        return response()->json(['data' => $this->detail($doc, $request)]);
    }

    public function signatureImage(PetDocument $petDocument, string $signatureRole): StreamedResponse
    {
        $sig = PetDocumentSignature::where('pet_document_id', $petDocument->id)->where('role', $signatureRole)->firstOrFail();

        return Storage::disk(config('filesystems.attachments_disk'))->response($sig->image_path, null, ['Content-Type' => 'image/png', 'Cache-Control' => 'private, max-age=86400']);
    }

    public function pdf(Request $request, PetDocument $petDocument, DocumentPdf $pdf): Response|StreamedResponse
    {
        $name = Str::slug("{$petDocument->title} {$petDocument->snapshot['pet']['name']}").'.pdf';

        activity('documents')->performedOn($petDocument)->causedBy($request->user())->event('exported')->log('Descargó el documento en PDF');

        // The signed file is the original: always served as stored, never regenerated.
        if ($petDocument->pdf_path !== null && $petDocument->status === PetDocument::SIGNED) {
            return Storage::disk(config('filesystems.attachments_disk'))->response($petDocument->pdf_path, $name, ['Content-Type' => 'application/pdf', 'Cache-Control' => 'private, no-store']);
        }

        return response($pdf->bytes($petDocument->load('signatures')), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "inline; filename=\"{$name}\"",
            'Cache-Control' => 'private, no-store',
        ]);
    }

    /** Send the document to the owner to sign from their phone (D3). */
    public function sendRemote(Request $request, PetDocument $petDocument, RemoteSigning $remote): JsonResponse
    {
        $data = $request->validate(['channel' => ['required', Rule::in(['whatsapp', 'email', 'link'])]], [], ['channel' => 'canal']);
        $sent = $remote->send($petDocument, $data['channel'], $request->user(), $this->clinic($request));

        return response()->json(['data' => [
            'url' => $sent['url'],
            'text' => $sent['text'],
            'whatsapp_url' => $sent['whatsapp_url'],
            'document' => $this->detail($petDocument->fresh(), $request),
        ]], 201);
    }

    public function revokeRemote(Request $request, PetDocument $petDocument, RemoteSigning $remote): JsonResponse
    {
        $remote->revoke($petDocument);

        return response()->json(['data' => $this->detail($petDocument->fresh(), $request)]);
    }

    public function void(Request $request, PetDocument $petDocument): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:500']], [], ['reason' => 'motivo']);
        $user = $request->user();

        abort_unless($this->canVoid($user, $petDocument), 403, 'Solo quien creó el documento, el veterinario responsable o un administrador puede anularlo.');
        if ($petDocument->status === PetDocument::VOIDED) {
            throw ValidationException::withMessages(['reason' => 'El documento ya está anulado.']);
        }

        $petDocument->update(['status' => PetDocument::VOIDED, 'voided_at' => now(), 'voided_by' => $user->id, 'void_reason' => $data['reason']]);
        app(RemoteSigning::class)->revoke($petDocument); // a pending link must not open a voided document

        return response()->json(['data' => $this->detail($petDocument->fresh(), $request)]);
    }

    // ------------------------------------------------------------------ helpers

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        $type = (string) $request->input('type');
        if (DocumentTypes::get($type) === null) {
            throw ValidationException::withMessages(['type' => 'Elige un tipo de documento.']);
        }

        return $request->validate([
            'type' => ['required', 'string'],
            'vet_id' => ['nullable', 'uuid'],
            'with_witness' => ['nullable', 'boolean'],
            'data' => ['nullable', 'array'],
            ...$this->renderer->rules($type),
        ], [], $this->renderer->attributes($type));
    }

    /** @param array<string, mixed> $data */
    private function snapshotFor(array $data, Clinic $clinic, Pet $pet, User $vet): array
    {
        $snapshot = $this->renderer->snapshot($data['type'], $data['data'] ?? [], $clinic, $pet, $vet);

        // The witness signs only when the vet asks for one: then it is required.
        if (isset($snapshot['signers']['witness'])) {
            if ($data['with_witness'] ?? false) {
                $snapshot['signers']['witness']['required'] = true;
            } else {
                unset($snapshot['signers']['witness']);
            }
        }

        return $snapshot;
    }

    private function vet(string $id): User
    {
        abort_unless(ClinicMembership::where('user_id', $id)->where('status', ClinicMembership::STATUS_ACTIVE)->exists(), 422, 'El veterinario no pertenece a la clínica.');

        return User::findOrFail($id);
    }

    private function canVoid(User $user, PetDocument $doc): bool
    {
        return $doc->created_by === $user->id || $doc->vet_id === $user->id || $user->can(Permission::ClinicalRecordsVoid->value);
    }

    /** @return array<string, mixed> */
    private function summary(PetDocument $d, Clinic $clinic): array
    {
        $signed = $d->signatures->pluck('role')->all();

        return [
            'id' => $d->id,
            'type' => $d->type,
            'title' => $d->title,
            'short' => DocumentTypes::get($d->type)['short'] ?? $d->title,
            'status' => ['value' => $d->status, 'label' => PetDocument::STATUSES[$d->status]],
            'vet' => $d->vet?->only(['id', 'name']),
            'signatures' => count($signed),
            'signers' => count($d->snapshot['signers'] ?? []),
            'pending' => collect($d->snapshot['signers'] ?? [])->filter(fn ($s, $r) => $s['required'] && ! in_array($r, $signed, true))->keys()->values(),
            'created_at' => ClinicTime::iso($d->created_at, $clinic),
            'signed_at' => ClinicTime::iso($d->signed_at, $clinic),
            'voided_at' => ClinicTime::iso($d->voided_at, $clinic),
        ];
    }

    /** @return array<string, mixed> */
    private function detail(PetDocument $d, Request $request): array
    {
        $clinic = $this->clinic($request);
        $d->loadMissing(['signatures', 'vet:id,name', 'creator:id,name', 'owner']);
        $user = $request->user();

        return [
            ...$this->summary($d, $clinic),
            'pet_id' => $d->pet_id,
            'owner_id' => $d->owner_id,
            'data' => $d->data,
            'snapshot' => $d->snapshot,
            'with_witness' => isset($d->snapshot['signers']['witness']),
            'created_by' => $d->creator?->only(['id', 'name']),
            'signature_list' => $d->signatures->map(fn (PetDocumentSignature $s) => [
                'role' => $s->role,
                'name' => $s->signer_name,
                'document' => $s->signer_document,
                'method' => $s->method,
                'method_label' => DocumentTypes::METHOD_LABELS[$s->method] ?? $s->method,
                'signed_at' => ClinicTime::iso($s->signed_at, $clinic),
                'image_url' => "documents/{$d->id}/signatures/{$s->role}",
            ])->values(),
            'remote' => ($r = PetDocumentRequest::where('pet_document_id', $d->id)->whereNull('used_at')->whereNull('revoked_at')->latest()->first()) ? [
                'channel' => $r->channel,
                'sent_at' => ClinicTime::iso($r->created_at, $clinic),
                'expires_at' => ClinicTime::iso($r->expires_at, $clinic),
                'active' => $r->isActive(),
                'attempts' => $r->attempts,
            ] : null,
            'owner_contact' => ['has_email' => filled($d->owner?->email), 'has_phone' => filled($d->owner?->mobile_phone)],
            'content_hash' => $d->content_hash,
            'pdf_sha256' => $d->pdf_sha256,
            'void_reason' => $d->void_reason,
            'can' => [
                'edit' => $d->isEditable() && $user->can(Permission::ClinicalRecordsCreate->value),
                'sign_as_vet' => $user->id === $d->vet_id,
                'send_remote' => $user->can(Permission::ClinicalRecordsCreate->value),
                'void' => $d->status !== PetDocument::VOIDED && $this->canVoid($user, $d),
            ],
        ];
    }

    private function clinic(Request $request): Clinic
    {
        return $request->attributes->get('clinic');
    }
}
