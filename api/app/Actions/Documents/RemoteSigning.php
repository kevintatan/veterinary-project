<?php

namespace App\Actions\Documents;

use App\Models\Clinic;
use App\Models\Documents\PetDocument;
use App\Models\Documents\PetDocumentRequest;
use App\Models\Owner;
use App\Models\User;
use App\Notifications\DocumentSignRequestNotification;
use App\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Remote signing (Documentos y firmas, D3): the clinic sends the owner a
 * one-use link; the owner reads the document on their phone, proves who they
 * are with the last 4 digits of their ID document and signs.
 *
 * Sending locks the text (the owner signs exactly what was sent). The link
 * lasts 48 h, is single use and locks after 5 wrong ID attempts.
 */
class RemoteSigning
{
    public function __construct(private readonly SignPetDocument $sign, private readonly TenantContext $tenant) {}

    /**
     * @return array{request: PetDocumentRequest, url: string, text: string, whatsapp_url: ?string}
     */
    public function send(PetDocument $doc, string $channel, User $by, Clinic $clinic): array
    {
        if (! in_array($doc->status, [PetDocument::DRAFT, PetDocument::SIGNING], true)) {
            throw ValidationException::withMessages(['channel' => 'El documento ya está firmado o anulado.']);
        }
        if ($doc->signatures()->where('role', 'owner')->exists()) {
            throw ValidationException::withMessages(['channel' => 'El tutor ya firmó este documento.']);
        }

        $owner = Owner::findOrFail($doc->owner_id);
        if ($channel === 'email' && blank($owner->email)) {
            throw ValidationException::withMessages(['channel' => 'El tutor no tiene correo registrado.']);
        }
        if ($channel === 'whatsapp' && blank($owner->mobile_phone)) {
            throw ValidationException::withMessages(['channel' => 'El tutor no tiene celular registrado.']);
        }

        $token = Str::random(48);

        $request = DB::transaction(function () use ($doc, $channel, $by, $token) {
            $this->sign->lock($doc);
            PetDocumentRequest::where('pet_document_id', $doc->id)->whereNull('used_at')->whereNull('revoked_at')->update(['revoked_at' => now()]);

            return PetDocumentRequest::create([
                'pet_document_id' => $doc->id,
                'role' => 'owner',
                'token_hash' => PetDocumentRequest::hash($token),
                'channel' => $channel,
                'expires_at' => now()->addHours(PetDocumentRequest::HOURS),
                'created_by' => $by->id,
            ]);
        });

        $url = rtrim((string) config('app.frontend_url'), '/').'/firmar/'.$token;
        $first = Str::of($owner->full_name)->trim()->explode(' ')->first();
        $text = "Hola {$first}, te escribimos de *{$clinic->name}*. Te enviamos el documento \"{$doc->title}\" de {$doc->snapshot['pet']['name']} para que lo leas y lo firmes desde tu celular: {$url} (el enlace vence en ".PetDocumentRequest::HOURS.' horas).';

        if ($channel === 'email') {
            Notification::route('mail', $owner->email)->notify(new DocumentSignRequestNotification($clinic, $owner->full_name, $doc->title, $doc->snapshot['pet']['name'], $url));
        }

        return [
            'request' => $request,
            'url' => $url,
            'text' => $text,
            'whatsapp_url' => $channel === 'whatsapp' ? 'https://wa.me/'.ltrim((string) $owner->mobile_phone, '+').'?text='.rawurlencode($text) : null,
        ];
    }

    public function revoke(PetDocument $doc): void
    {
        PetDocumentRequest::where('pet_document_id', $doc->id)->whereNull('used_at')->whereNull('revoked_at')->update(['revoked_at' => now()]);
    }

    /**
     * Runs $callback inside the request's clinic with the request and its
     * document. Expired, used, revoked or locked links answer 410.
     */
    public function withToken(string $token, callable $callback): mixed
    {
        $clinicId = DB::table('pet_document_requests')->where('token_hash', PetDocumentRequest::hash($token))->value('clinic_id');
        abort_if($clinicId === null, 404, 'Este enlace no existe.');

        return $this->tenant->runAs($clinicId, function () use ($token, $callback) {
            $req = PetDocumentRequest::with('document')->where('token_hash', PetDocumentRequest::hash($token))->firstOrFail();
            $doc = $req->document;

            abort_if($doc->status === PetDocument::SIGNED || $req->used_at !== null, 410, 'Este documento ya fue firmado. Gracias.');
            abort_if($doc->status === PetDocument::VOIDED || $req->revoked_at !== null, 410, 'Este enlace ya no es válido. Pídele a la clínica uno nuevo.');
            abort_if($req->expires_at->isPast(), 410, 'Este enlace venció. Pídele a la clínica uno nuevo.');
            abort_if($req->attempts >= PetDocumentRequest::MAX_ATTEMPTS, 410, 'El enlace se bloqueó por intentos fallidos. Pídele a la clínica uno nuevo.');

            return $callback($req, $doc);
        });
    }

    /** The owner signs from the link: checks the last 4 digits of their ID first. */
    public function signWithToken(string $token, string $last4, string $image, Request $request): PetDocument
    {
        return $this->withToken($token, function (PetDocumentRequest $req, PetDocument $doc) use ($last4, $image, $request) {
            $expected = substr(preg_replace('/\D/', '', (string) ($doc->snapshot['owner']['document_number'] ?? '')), -4);

            if ($expected === '' || ! hash_equals($expected, $last4)) {
                // Counted outside any transaction: a wrong attempt must stick.
                $req->increment('attempts');
                $left = PetDocumentRequest::MAX_ATTEMPTS - $req->attempts;

                throw ValidationException::withMessages(['document_last4' => $left > 0
                    ? "Los dígitos no coinciden con el documento registrado en la clínica. Te quedan {$left} intento(s)."
                    : 'Los dígitos no coinciden. El enlace se bloqueó: pídele a la clínica uno nuevo.']);
            }

            return ($this->sign)($doc, ['role' => 'owner', 'image' => $image], null, $request, 'remote_link');
        });
    }
}
