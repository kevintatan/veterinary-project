<?php

namespace App\Http\Controllers\Api\V1\Portal;

use App\Actions\Documents\SignPetDocument;
use App\Http\Controllers\Controller;
use App\Models\Clinic;
use App\Models\Documents\PetDocument;
use App\Models\Documents\PetDocumentRequest;
use App\Models\TutorAccount;
use App\Portal\TutorPortal;
use App\Support\ClinicTime;
use App\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The owner's consents in the portal (D3): the ones the clinic sent to sign
 * (the portal session already proves who they are) and the signed ones, with
 * the PDF. Drafts and voided documents are never shown.
 */
class PortalDocumentController extends Controller
{
    public function __construct(private readonly TutorPortal $portal, private readonly TenantContext $tenant) {}

    public function index(Request $request): JsonResponse
    {
        $result = [];

        foreach ($this->portal->links($request->user()) as $link) {
            $this->tenant->runAs($link->clinic_id, function () use ($link, &$result) {
                $clinic = Clinic::find($link->clinic_id);
                $docs = PetDocument::with('signatures')->where('owner_id', $link->owner_id)->whereIn('status', [PetDocument::SIGNING, PetDocument::SIGNED])->latest()->get();

                foreach ($docs as $doc) {
                    $toSign = $this->awaitingOwner($doc);
                    if ($doc->status === PetDocument::SIGNING && ! $toSign) {
                        continue; // in progress at the clinic, nothing for the owner to do
                    }
                    $result[] = [
                        'id' => $doc->id,
                        'title' => $doc->title,
                        'pet' => $doc->snapshot['pet']['name'],
                        'clinic' => $clinic->name,
                        'to_sign' => $toSign,
                        'signed_at' => ClinicTime::iso($doc->signed_at, $clinic),
                        'created_at' => ClinicTime::iso($doc->created_at, $clinic),
                    ];
                }
            });
        }

        return response()->json(['data' => $result]);
    }

    public function show(Request $request, string $portalDocument): JsonResponse
    {
        return $this->withDocument($request->user(), $portalDocument, fn (PetDocument $doc) => response()->json(['data' => [
            'id' => $doc->id,
            'title' => $doc->title,
            'status' => $doc->status,
            'to_sign' => $this->awaitingOwner($doc),
            // Never the bare ID number: it is what the link signer types to prove who they are.
            'snapshot' => [...collect($doc->snapshot)->only(['title', 'date', 'clinic', 'pet', 'vet', 'blocks'])->all(), 'owner' => ['name' => $doc->snapshot['owner']['name']]],
            'signatures' => $doc->signatures->map(fn ($s) => ['role' => $s->role, 'name' => $s->signer_name])->values(),
        ]]));
    }

    public function sign(Request $request, string $portalDocument, SignPetDocument $sign): JsonResponse
    {
        $data = $request->validate([
            'image' => ['required', 'string', 'max:1000000'],
            'accepted' => ['accepted'],
        ], ['accepted.accepted' => 'Confirma que leíste y aceptas el documento.'], ['image' => 'firma']);

        return $this->withDocument($request->user(), $portalDocument, function (PetDocument $doc) use ($data, $request, $sign) {
            abort_unless($this->awaitingOwner($doc), 422, 'Este documento no está pendiente de tu firma.');
            $signed = $sign($doc, ['role' => 'owner', 'image' => $data['image']], null, $request, 'portal');

            return response()->json(['data' => ['status' => $signed->status, 'signed' => true]]);
        });
    }

    public function pdf(Request $request, string $portalDocument): StreamedResponse
    {
        return $this->withDocument($request->user(), $portalDocument, function (PetDocument $doc) {
            abort_unless($doc->status === PetDocument::SIGNED && $doc->pdf_path !== null, 404);

            return Storage::disk(config('filesystems.attachments_disk'))->response($doc->pdf_path, Str::slug("{$doc->title} {$doc->snapshot['pet']['name']}").'.pdf', ['Content-Type' => 'application/pdf', 'Cache-Control' => 'private, no-store']);
        });
    }

    /** Sent by the clinic (active link) and not signed by the owner yet. */
    private function awaitingOwner(PetDocument $doc): bool
    {
        return $doc->status === PetDocument::SIGNING
            && ! $doc->signatures->contains('role', 'owner')
            && PetDocumentRequest::where('pet_document_id', $doc->id)->whereNull('used_at')->whereNull('revoked_at')->where('expires_at', '>', now())->where('attempts', '<', PetDocumentRequest::MAX_ATTEMPTS)->exists();
    }

    /** Only documents of an owner linked to this account; anything else is 404. */
    private function withDocument(TutorAccount $account, string $id, callable $callback): mixed
    {
        $row = DB::table('pet_documents')->where('id', $id)->first(['clinic_id', 'owner_id', 'status']);
        abort_if($row === null || ! in_array($row->status, [PetDocument::SIGNING, PetDocument::SIGNED], true), 404);
        abort_unless($this->portal->links($account)->contains(fn ($l) => $l->clinic_id === $row->clinic_id && $l->owner_id === $row->owner_id), 404);

        return $this->tenant->runAs($row->clinic_id, fn () => $callback(PetDocument::with('signatures')->findOrFail($id)));
    }
}
