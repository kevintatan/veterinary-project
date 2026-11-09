<?php

namespace App\Clinical;

use App\Models\Branch;
use App\Models\Clinic;
use App\Models\Clinical\ClinicalRecord;
use App\Models\Pet;
use App\Models\User;
use App\Support\ClinicTime;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Printable clinical history (whole or one record): clinic letterhead,
 * patient and owner, active alerts, records with author and professional
 * license, voided ones marked. Every export is written to the audit log.
 */
class ClinicalPdf
{
    /**
     * @param  Collection<int, ClinicalRecord>  $records
     */
    public function render(Clinic $clinic, User $user, Pet $pet, Collection $records, string $title, ?string $period = null): Response
    {
        $pet->loadMissing(['species', 'breed', 'owner', 'alerts' => fn ($q) => $q->where('is_active', true)]);
        $records->loadMissing(['author', 'voider', 'attachments', ...RecordTypes::allRelations()]);

        $branch = $records->first()?->branch_id ? Branch::find($records->first()->branch_id) : Branch::where('is_active', true)->orderBy('created_at')->first();
        $generatedAt = Carbon::now($clinic->timezone);

        $pdf = Pdf::loadView('pdf.clinical-history', [
            'clinic' => $clinic,
            'branch' => $branch,
            'logo' => $this->logo($clinic),
            'pet' => $pet,
            'owner' => $pet->owner,
            'title' => $title,
            'period' => $period,
            'records' => $records->map(fn (ClinicalRecord $r) => $this->recordData($r, $clinic)),
            'generatedAt' => $generatedAt,
            'generatedBy' => $user->name,
        ])->setPaper('letter')->setOptions([
            // Embed only the glyphs used: ~50 KB instead of ~900 KB (it travels by WhatsApp).
            'isFontSubsettingEnabled' => true,
            'isRemoteEnabled' => false,
        ]);

        // Page numbers on every page (canvas text: no PHP inside templates).
        $dompdf = $pdf->getDomPDF();
        $dompdf->render();
        $canvas = $dompdf->getCanvas();
        $font = $dompdf->getFontMetrics()->getFont('DejaVu Sans');
        $canvas->page_text(
            $canvas->get_width() - 120,
            $canvas->get_height() - 28,
            'Página {PAGE_NUM} de {PAGE_COUNT}',
            $font,
            7,
            [0.45, 0.47, 0.5],
        );

        activity('clinical')
            ->performedOn($pet)
            ->causedBy($user)
            ->event('exported')
            ->withProperties(['records' => $records->count(), 'title' => $title, 'period' => $period])
            ->log('Exportó la historia clínica en PDF');

        $filename = Str::slug("{$title} {$pet->name} {$generatedAt->toDateString()}").'.pdf';

        return response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "inline; filename=\"{$filename}\"",
            'Cache-Control' => 'private, no-store',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function recordData(ClinicalRecord $record, Clinic $clinic): array
    {
        $type = RecordTypes::for($record->type);

        return [
            'type' => $record->type,
            'type_label' => $record->typeLabel(),
            'summary' => $type->summary($record),
            'occurred_at' => $record->occurred_at->copy()->setTimezone($clinic->timezone),
            'author' => $record->author?->name,
            'license' => $record->author?->professional_license,
            'next_control_at' => $record->next_control_at,
            'next_control_note' => $record->next_control_note,
            'voided' => $record->isVoided() ? [
                'at' => ClinicTime::iso($record->voided_at, $clinic),
                'reason' => $record->void_reason,
                'by' => $record->voider?->name,
            ] : null,
            'attachments' => $record->attachments->pluck('original_name')->all(),
            'detail' => $type->present($record),
        ];
    }

    /** Clinic logo as a data URI (dompdf runs with remote access disabled). */
    private function logo(Clinic $clinic): ?string
    {
        if ($clinic->logo_path === null) {
            return null;
        }

        $disk = Storage::disk(config('filesystems.attachments_disk'));

        if (! $disk->exists($clinic->logo_path)) {
            return null;
        }

        return 'data:'.($disk->mimeType($clinic->logo_path) ?: 'image/png').';base64,'.base64_encode($disk->get($clinic->logo_path));
    }
}
