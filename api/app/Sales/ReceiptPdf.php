<?php

namespace App\Sales;

use App\Models\Clinic;
use App\Models\Sales\Payment;
use App\Models\Sales\Sale;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

/**
 * Payment receipt of a sale (half letter). Says clearly that it is NOT an
 * electronic invoice (docs/inventario.md): electronic invoicing is Fase 5b.
 */
final class ReceiptPdf
{
    public function bytes(Sale $sale): string
    {
        $clinic = Clinic::findOrFail($sale->clinic_id);
        $disk = Storage::disk(config('filesystems.attachments_disk'));
        $sale->load(['items', 'payments', 'branch', 'seller']);
        $tz = $clinic->timezone;

        $pdf = Pdf::loadView('pdf.receipt', [
            'sale' => $sale,
            'clinic' => $clinic,
            'logo' => $clinic->logo_path && $disk->exists($clinic->logo_path)
                ? 'data:'.($disk->mimeType($clinic->logo_path) ?: 'image/png').';base64,'.base64_encode($disk->get($clinic->logo_path))
                : null,
            'soldAt' => $sale->sold_at->copy()->setTimezone($tz),
            'payments' => $sale->payments->whereNull('voided_at')->map(fn (Payment $p) => [
                'method' => Payment::METHODS[$p->method],
                'reference' => $p->reference,
                'amount' => (float) $p->amount,
                'at' => $p->paid_at->copy()->setTimezone($tz)->format('d/m/Y'),
            ])->values(),
            'voidedBy' => $sale->voided_by ? User::whereKey($sale->voided_by)->value('name') : null,
            'money' => fn (float|string $v) => '$ '.number_format((float) $v, 0, ',', '.'),
            'qty' => fn (float|string $v) => rtrim(rtrim(number_format((float) $v, 3, ',', '.'), '0'), ','),
        ]);
        $pdf->setPaper([0, 0, 396, 612]); // half letter (5.5 × 8.5 in)

        return $pdf->output();
    }
}
