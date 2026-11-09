<?php

namespace App\Actions\Documents;

use App\Documents\DocumentPdf;
use App\Documents\DocumentRenderer;
use App\Models\Documents\PetDocument;
use App\Models\Documents\PetDocumentRequest;
use App\Models\Documents\PetDocumentSignature;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Adds one signature (owner, vet or witness) to a document.
 *
 * - The first signature locks the content: from then on the text cannot
 *   change, and each signature records the hash of what it signed.
 * - The vet signature is the responsible vet's own session.
 * - When every required signer has signed, the PDF is generated once, hashed
 *   and stored: that file is the signed document.
 *
 * The stroke is re-encoded (PNG, max 900 px) and an empty pad is rejected.
 * Evidence kept for each signature (Ley 527 de 1999, reliability): name and
 * document, content hash, date and time, IP, device, and the staff session
 * where it was collected.
 */
class SignPetDocument
{
    private const MAX_WIDTH = 900;

    public function __construct(private readonly DocumentRenderer $renderer, private readonly DocumentPdf $pdf) {}

    /**
     * @param  array{role: string, image: string, signer_name?: ?string, signer_document?: ?string}  $input
     * @param  ?User  $user  staff session where it is signed; null when the owner signs from their phone
     * @param  string  $method  in_person | remote_link (link + ID digits) | portal (owner portal session)
     */
    public function __invoke(PetDocument $document, array $input, ?User $user, Request $request, string $method = 'in_person'): PetDocument
    {
        $role = $input['role'];

        if ($user === null && $role !== 'owner') {
            throw ValidationException::withMessages(['role' => 'A distancia solo firma el tutor.']);
        }

        return DB::transaction(function () use ($document, $input, $role, $user, $request, $method) {
            /** @var PetDocument $doc */
            $doc = PetDocument::whereKey($document->id)->lockForUpdate()->firstOrFail();

            if (! in_array($doc->status, [PetDocument::DRAFT, PetDocument::SIGNING], true)) {
                throw ValidationException::withMessages(['role' => $doc->status === PetDocument::SIGNED ? 'El documento ya está firmado.' : 'El documento está anulado.']);
            }

            $signers = $doc->snapshot['signers'] ?? [];
            if (! isset($signers[$role])) {
                throw ValidationException::withMessages(['role' => 'Este documento no lleva esa firma.']);
            }
            if ($doc->signatures()->where('role', $role)->exists()) {
                throw ValidationException::withMessages(['role' => 'Esa firma ya está registrada.']);
            }

            [$name, $docNumber, $signerUser] = match ($role) {
                'owner' => [$doc->snapshot['owner']['name'], $doc->snapshot['owner']['document'], null],
                'vet' => $user?->id === $doc->vet_id
                    ? [$doc->snapshot['vet']['name'], 'T.P. '.$doc->snapshot['vet']['license'], $user->id]
                    : throw ValidationException::withMessages(['role' => "Esta firma la hace {$doc->snapshot['vet']['name']} desde su propia sesión."]),
                'witness' => [
                    trim((string) ($input['signer_name'] ?? '')) ?: throw ValidationException::withMessages(['signer_name' => 'Escribe el nombre del testigo.']),
                    trim((string) ($input['signer_document'] ?? '')) ?: throw ValidationException::withMessages(['signer_document' => 'Escribe el documento del testigo.']),
                    null,
                ],
            };

            $this->lock($doc); // first signature: the content is locked from here on

            $path = "clinics/{$doc->clinic_id}/documents/{$doc->id}/{$role}-".Str::uuid7().'.png';
            Storage::disk(config('filesystems.attachments_disk'))->put($path, $this->strokePng($input['image']));

            PetDocumentSignature::create([
                'pet_document_id' => $doc->id,
                'role' => $role,
                'signer_name' => $name,
                'signer_document' => $docNumber,
                'signer_user_id' => $signerUser,
                'image_path' => $path,
                'method' => $method,
                'content_hash' => $doc->content_hash,
                'ip' => $request->ip(),
                'user_agent' => Str::limit((string) $request->userAgent(), 297),
                'collected_by' => $user?->id,
                'signed_at' => now(),
            ]);

            // A signature closes any pending remote link for that role (it can't be used twice).
            PetDocumentRequest::where('pet_document_id', $doc->id)->where('role', $role)->whereNull('used_at')->whereNull('revoked_at')->update(['used_at' => now()]);

            $signed = $doc->signatures()->pluck('role')->all();
            $missing = collect($signers)->filter(fn ($s, $r) => $s['required'] && ! in_array($r, $signed, true));

            if ($missing->isEmpty()) {
                $this->finalize($doc);
            }

            return $doc->fresh('signatures');
        });
    }

    /**
     * Freeze the text: done by the first signature, or when the document is sent
     * to be signed remotely (the owner must sign exactly what was sent).
     */
    public function lock(PetDocument $doc): void
    {
        if ($doc->status === PetDocument::DRAFT) {
            $doc->forceFill(['status' => PetDocument::SIGNING, 'content_hash' => $this->renderer->contentHash($doc->snapshot)])->save();
        }
    }

    private function finalize(PetDocument $doc): void
    {
        $doc->forceFill(['status' => PetDocument::SIGNED, 'signed_at' => now()])->save();

        $bytes = $this->pdf->bytes($doc->fresh('signatures'));
        $path = "clinics/{$doc->clinic_id}/documents/{$doc->id}/documento-firmado.pdf";
        Storage::disk(config('filesystems.attachments_disk'))->put($path, $bytes);

        $doc->forceFill(['pdf_path' => $path, 'pdf_sha256' => hash('sha256', $bytes)])->save();
    }

    /** data:image/png;base64,… → re-encoded PNG. Rejects non-images and empty pads. */
    private function strokePng(string $dataUrl): string
    {
        $invalid = ValidationException::withMessages(['image' => 'La firma no es válida. Vuelve a firmar.']);

        if (! preg_match('#^data:image/(png|jpeg|webp);base64,([A-Za-z0-9+/=]+)$#', $dataUrl, $m)) {
            throw $invalid;
        }
        $raw = base64_decode($m[2], true);
        if ($raw === false || strlen($raw) > 700_000) {
            throw $invalid;
        }
        $src = @imagecreatefromstring($raw);
        if ($src === false) {
            throw $invalid;
        }

        [$w, $h] = [imagesx($src), imagesy($src)];

        // An empty or almost empty pad is not a signature: count the inked pixels on a grid.
        $inked = 0;
        $step = max(1, (int) floor(min($w, $h) / 120));
        for ($x = 0; $x < $w; $x += $step) {
            for ($y = 0; $y < $h; $y += $step) {
                $c = imagecolorsforindex($src, imagecolorat($src, $x, $y));
                if ($c['alpha'] < 100 && ($c['red'] + $c['green'] + $c['blue']) < 450) {
                    $inked++;
                }
            }
        }
        if ($inked < 40) {
            imagedestroy($src);
            throw ValidationException::withMessages(['image' => 'La firma está vacía o es muy corta. Firma de nuevo dentro del recuadro.']);
        }

        $scale = min(1, self::MAX_WIDTH / $w);
        $img = imagescale($src, max(1, (int) round($w * $scale)), max(1, (int) round($h * $scale)), IMG_BICUBIC);
        imagedestroy($src);
        imagealphablending($img, false); // keep the transparent background
        imagesavealpha($img, true);

        ob_start();
        imagepng($img, null, 8);
        $bytes = (string) ob_get_clean();
        imagedestroy($img);

        return $bytes;
    }
}
