<?php

namespace App\Documents;

use App\Models\Clinic;
use App\Models\Documents\PetDocument;
use App\Models\Documents\PetDocumentSignature;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * The PDF of a consent: letterhead, frozen text, signatures and an evidence
 * page. A draft prints with a "BORRADOR" mark (for reading it with the owner
 * before signing); the signed one is generated once and stored.
 */
class DocumentPdf
{
    public function bytes(PetDocument $doc): string
    {
        $clinic = Clinic::findOrFail($doc->clinic_id);
        $disk = Storage::disk(config('filesystems.attachments_disk'));
        $tz = $clinic->timezone;

        $signatures = $doc->signatures->sortBy(fn (PetDocumentSignature $s) => array_search($s->role, ['owner', 'vet', 'witness'], true))->values();

        $pdf = Pdf::loadView('pdf.document', [
            'doc' => $doc,
            's' => $doc->snapshot,
            'clinic' => $clinic,
            'logo' => $clinic->logo_path && $disk->exists($clinic->logo_path)
                ? 'data:'.($disk->mimeType($clinic->logo_path) ?: 'image/png').';base64,'.base64_encode($disk->get($clinic->logo_path))
                : null,
            'draft' => $doc->status === PetDocument::DRAFT || $doc->status === PetDocument::SIGNING,
            'voided' => $doc->status === PetDocument::VOIDED,
            // jsonb doesn't keep key order (PostgreSQL sorts keys by length): order the signers explicitly.
            'signers' => collect(['owner', 'vet', 'witness'])->filter(fn ($r) => isset($doc->snapshot['signers'][$r]))->mapWithKeys(fn ($r) => [$r => $doc->snapshot['signers'][$r]])->map(function ($signer, $role) use ($signatures, $disk, $tz) {
                $sig = $signatures->firstWhere('role', $role);

                return [
                    'role' => $role,
                    'label' => $signer['label'],
                    'required' => $signer['required'],
                    'name' => $sig?->signer_name,
                    'document' => $sig?->signer_document,
                    'image' => $sig && $disk->exists($sig->image_path) ? 'data:image/png;base64,'.base64_encode($disk->get($sig->image_path)) : null,
                    'signed_at' => $sig?->signed_at?->copy()->setTimezone($tz),
                ];
            })->values(),
            'evidence' => $signatures->map(fn (PetDocumentSignature $s) => [
                'label' => DocumentTypes::ROLE_LABELS[$s->role] ?? $s->role,
                'name' => $s->signer_name,
                'document' => $s->signer_document,
                'signed_at' => $s->signed_at->copy()->setTimezone($tz),
                'method' => DocumentTypes::METHOD_LABELS[$s->method] ?? $s->method,
                'ip' => $s->ip,
                'device' => $this->device((string) $s->user_agent),
                'hash' => $s->content_hash,
            ]),
            'generatedAt' => Carbon::now($tz),
        ])->setPaper('letter')->setOptions(['isFontSubsettingEnabled' => true, 'isRemoteEnabled' => false]);

        $dompdf = $pdf->getDomPDF();
        $dompdf->render();
        $canvas = $dompdf->getCanvas();
        $canvas->page_text($canvas->get_width() - 120, $canvas->get_height() - 28, 'Página {PAGE_NUM} de {PAGE_COUNT}', $dompdf->getFontMetrics()->getFont('DejaVu Sans'), 7, [0.45, 0.47, 0.5]);

        return $dompdf->output();
    }

    private function device(string $ua): string
    {
        $os = match (true) {
            str_contains($ua, 'iPhone') || str_contains($ua, 'iPad') => 'iOS',
            str_contains($ua, 'Android') => 'Android',
            str_contains($ua, 'Windows') => 'Windows',
            str_contains($ua, 'Mac OS') => 'macOS',
            str_contains($ua, 'Linux') => 'Linux',
            default => 'desconocido',
        };
        $browser = match (true) {
            str_contains($ua, 'Edg/') => 'Edge',
            str_contains($ua, 'Chrome/') => 'Chrome',
            str_contains($ua, 'Firefox/') => 'Firefox',
            str_contains($ua, 'Safari/') => 'Safari',
            default => 'navegador desconocido',
        };

        return "{$browser} en {$os}";
    }
}
