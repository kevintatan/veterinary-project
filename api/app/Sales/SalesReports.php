<?php

namespace App\Sales;

use App\Models\Clinic;
use App\Models\Sales\Payment;
use App\Models\Sales\Sale;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Money reports (docs/inventario.md, V4), inside the clinic's tenant context.
 * Two different numbers, never mixed:
 *  - "ventas"    = what was sold in the period (non-voided sales by sale date);
 *  - "recaudado" = money received in the period (non-voided payments by payment
 *    date), which may pay sales from other days.
 */
final class SalesReports
{
    /** Daily close: the day's money by payment method, pending, voids and who received. */
    public function dailyClose(Clinic $clinic, string $date, ?string $branchId): array
    {
        [$from, $to] = $this->day($clinic, $date);

        $payments = $this->payments($branchId)->whereNull('payments.voided_at')->whereBetween('payments.paid_at', [$from, $to]);
        $byMethod = (clone $payments)->selectRaw('payments.method, count(*) as count, sum(payments.amount) as total')->groupBy('payments.method')->get()
            ->map(fn ($r) => ['method' => ['value' => $r->method, 'label' => Payment::METHODS[$r->method]], 'count' => (int) $r->count, 'total' => (float) $r->total])
            ->sortByDesc('total')->values();
        $collected = round((float) $byMethod->sum('total'), 2);

        // Payments received today for sales made on other days (abonos).
        $fromEarlier = (float) (clone $payments)->where('sales.sold_at', '<', $from)->sum('payments.amount');

        $byReceiver = (clone $payments)->selectRaw('payments.received_by, sum(payments.amount) as total, count(*) as count')->groupBy('payments.received_by')->get();
        $names = User::whereIn('id', $byReceiver->pluck('received_by'))->pluck('name', 'id');

        $sales = $this->sales($branchId)->whereBetween('sold_at', [$from, $to]);
        $live = (clone $sales)->where('status', '<>', Sale::VOID);
        $pending = (clone $live)->whereColumn('paid_total', '<', 'total')->orderBy('number')->get();

        $voidedSales = $this->sales($branchId)->where('status', Sale::VOID)->whereBetween('voided_at', [$from, $to])->orderBy('number')->get();
        $voidedPayments = $this->payments($branchId)->whereBetween('payments.voided_at', [$from, $to])->get(['payments.*', 'sales.number']);

        return [
            'date' => $date,
            'collected' => $collected,
            'collected_from_earlier_sales' => round($fromEarlier, 2),
            'by_method' => $byMethod,
            'by_receiver' => $byReceiver->map(fn ($r) => ['name' => $names[$r->received_by] ?? '—', 'count' => (int) $r->count, 'total' => (float) $r->total])->sortByDesc('total')->values(),
            'sales' => [
                'count' => (clone $live)->count(),
                'total' => round((float) (clone $live)->sum('total'), 2),
            ],
            'pending' => [
                'total' => round($pending->sum(fn (Sale $s) => $s->balance()), 2),
                'list' => $pending->map(fn (Sale $s) => ['id' => $s->id, 'number' => $s->number, 'customer' => $s->customer_snapshot['name'] ?? null, 'total' => (float) $s->total, 'balance' => $s->balance()])->values(),
            ],
            'voided_sales' => $voidedSales->map(fn (Sale $s) => ['id' => $s->id, 'number' => $s->number, 'customer' => $s->customer_snapshot['name'] ?? null, 'total' => (float) $s->total, 'paid' => (float) $s->paid_total, 'reason' => $s->void_reason])->values(),
            'voided_payments' => $voidedPayments->map(fn ($p) => ['number' => $p->number, 'method' => Payment::METHODS[$p->method], 'amount' => (float) $p->amount, 'reason' => $p->void_reason])->values(),
        ];
    }

    /** Sales report for a period, compared with the previous period of the same length. */
    public function period(Clinic $clinic, string $fromDate, string $toDate, ?string $branchId): array
    {
        [$from] = $this->day($clinic, $fromDate);
        [, $to] = $this->day($clinic, $toDate);
        $days = (int) round($from->diffInDays($to)) + 1;
        $prevFrom = $from->copy()->subDays($days);
        $prevTo = $from->copy()->subSecond();

        $current = $this->totals($branchId, $from, $to);
        $previous = $this->totals($branchId, $prevFrom, $prevTo);

        $live = fn () => $this->sales($branchId)->where('status', '<>', Sale::VOID)->whereBetween('sold_at', [$from, $to]);
        $tz = $clinic->timezone;

        // Per day, in the clinic's calendar (every day present, even with no sales).
        $soldByDay = $live()->selectRaw("to_char(sold_at AT TIME ZONE ?, 'YYYY-MM-DD') as d, sum(total) as total", [$tz])->groupBy('d')->pluck('total', 'd');
        $collectedByDay = $this->payments($branchId)->whereNull('payments.voided_at')->whereBetween('payments.paid_at', [$from, $to])
            ->selectRaw("to_char(payments.paid_at AT TIME ZONE ?, 'YYYY-MM-DD') as d, sum(payments.amount) as total", [$tz])->groupBy('d')->pluck('total', 'd');
        $daily = [];
        for ($d = $from->copy()->setTimezone($tz); $d->lte($to->copy()->setTimezone($tz)); $d->addDay()) {
            $key = $d->toDateString();
            $daily[] = ['date' => $key, 'sold' => (float) ($soldByDay[$key] ?? 0), 'collected' => (float) ($collectedByDay[$key] ?? 0)];
        }

        $items = DB::table('sale_items')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->where('sales.clinic_id', $clinic->id)
            ->where('sales.status', '<>', Sale::VOID)
            ->whereBetween('sales.sold_at', [$from, $to])
            ->when($branchId, fn ($q) => $q->where('sales.branch_id', $branchId));

        $top = fn (string $type) => (clone $items)->where('sale_items.item_type', $type)
            ->selectRaw('sale_items.description as name, sum(sale_items.quantity) as quantity, sum(sale_items.total) as total')
            ->groupBy('sale_items.description')->orderByDesc('total')->limit(10)->get()
            ->map(fn ($r) => ['name' => $r->name, 'quantity' => (float) $r->quantity, 'total' => (float) $r->total]);

        $byType = (clone $items)->selectRaw('sale_items.item_type as type, sum(sale_items.total) as total')->groupBy('sale_items.item_type')->pluck('total', 'type');

        $bySeller = $live()->selectRaw('sold_by, count(*) as count, sum(total) as total')->groupBy('sold_by')->get();
        $names = User::whereIn('id', $bySeller->pluck('sold_by'))->pluck('name', 'id');

        $byMethod = $this->payments($branchId)->whereNull('payments.voided_at')->whereBetween('payments.paid_at', [$from, $to])
            ->selectRaw('payments.method, sum(payments.amount) as total')->groupBy('payments.method')->get()
            ->map(fn ($r) => ['method' => ['value' => $r->method, 'label' => Payment::METHODS[$r->method]], 'total' => (float) $r->total])
            ->sortByDesc('total')->values();

        return [
            'from' => $fromDate,
            'to' => $toDate,
            'days' => $days,
            'summary' => $current,
            'previous' => $previous,
            'daily' => $daily,
            'top_services' => $top('service'),
            'top_products' => $top('product'),
            'by_type' => ['service' => (float) ($byType['service'] ?? 0), 'product' => (float) ($byType['product'] ?? 0), 'custom' => (float) ($byType['custom'] ?? 0)],
            'by_seller' => $bySeller->map(fn ($r) => ['name' => $names[$r->sold_by] ?? '—', 'count' => (int) $r->count, 'total' => (float) $r->total])->sortByDesc('total')->values(),
            'by_method' => $byMethod,
        ];
    }

    /** For the accountant: one row per line (items) or per payment, Excel in Spanish (";" and decimal comma). */
    public function csv(Clinic $clinic, string $kind, string $fromDate, string $toDate, ?string $branchId): string
    {
        [$from] = $this->day($clinic, $fromDate);
        [, $to] = $this->day($clinic, $toDate);
        $tz = $clinic->timezone;
        $n = fn ($v) => str_replace('.', ',', (string) round((float) $v, 2));
        $date = fn (?Carbon $d) => $d?->copy()->setTimezone($tz)->format('Y-m-d H:i') ?? '';

        if ($kind === 'payments') {
            $header = ['Fecha pago', 'Venta N.º', 'Cliente', 'Documento', 'Medio', 'Referencia', 'Valor', 'Recibió', 'Estado', 'Motivo anulación'];
            $rows = Payment::with(['sale', 'receiver:id,name'])
                ->whereBetween('paid_at', [$from, $to])
                ->when($branchId, fn ($q) => $q->whereHas('sale', fn ($s) => $s->where('branch_id', $branchId)))
                ->orderBy('paid_at')->get()
                ->map(fn (Payment $p) => [
                    $date($p->paid_at), $p->sale->number, $p->sale->customer_snapshot['name'] ?? 'Mostrador', $p->sale->customer_snapshot['document'] ?? '',
                    Payment::METHODS[$p->method], $p->reference ?? '', $n($p->amount), $p->receiver?->name ?? '', $p->voided_at ? 'Anulado' : 'Válido', $p->void_reason ?? '',
                ]);
        } else {
            $header = ['Fecha venta', 'Venta N.º', 'Estado', 'Cliente', 'Documento', 'Sede', 'Vendedor', 'Tipo', 'Descripción', 'Cantidad', 'Valor unitario', 'Descuento', 'IVA %', 'IVA incluido', 'Total ítem'];
            $types = ['product' => 'Producto', 'service' => 'Servicio', 'custom' => 'Cargo libre'];
            $rows = Sale::with(['items', 'branch:id,name', 'seller:id,name'])
                ->whereBetween('sold_at', [$from, $to])
                ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
                ->orderBy('number')->get()
                ->flatMap(fn (Sale $s) => $s->items->map(fn ($i) => [
                    $date($s->sold_at), $s->number, Sale::STATUSES[$s->status], $s->customer_snapshot['name'] ?? 'Mostrador', $s->customer_snapshot['document'] ?? '',
                    $s->branch?->name ?? '', $s->seller?->name ?? '', $types[$i->item_type], $i->description, $n($i->quantity), $n($i->unit_price), $n($i->discount), $n($i->tax_rate), $n($i->tax_amount), $n($i->total),
                ]));
        }

        $escape = fn ($v) => preg_match('/[;"\r\n]/', (string) $v) ? '"'.str_replace('"', '""', (string) $v).'"' : (string) $v;

        return "\xEF\xBB\xBF".collect([$header, ...$rows])->map(fn ($r) => implode(';', array_map($escape, $r)))->implode("\r\n")."\r\n";
    }

    // ------------------------------------------------------------------

    /** @return array<string, float|int> */
    private function totals(?string $branchId, Carbon $from, Carbon $to): array
    {
        $live = $this->sales($branchId)->where('status', '<>', Sale::VOID)->whereBetween('sold_at', [$from, $to]);
        $row = (clone $live)->selectRaw('count(*) as count, coalesce(sum(total), 0) as total, coalesce(sum(tax_total), 0) as tax, coalesce(sum(discount_total), 0) as discount, coalesce(sum(total - paid_total), 0) as pending')->first();
        $collected = (float) $this->payments($branchId)->whereNull('payments.voided_at')->whereBetween('payments.paid_at', [$from, $to])->sum('payments.amount');
        $count = (int) $row->count;

        return [
            'sales_count' => $count,
            'sold' => round((float) $row->total, 2),
            'tax' => round((float) $row->tax, 2),
            'discounts' => round((float) $row->discount, 2),
            'pending' => round((float) $row->pending, 2),
            'collected' => round($collected, 2),
            'average_ticket' => $count ? round((float) $row->total / $count, 0) : 0,
            'voided_count' => $this->sales($branchId)->where('status', Sale::VOID)->whereBetween('sold_at', [$from, $to])->count(),
        ];
    }

    /** @return Builder<Sale> */
    private function sales(?string $branchId): Builder
    {
        return Sale::query()->when($branchId, fn ($q) => $q->where('branch_id', $branchId));
    }

    /** Payments joined to their sale (branch filter, sale date). @return Builder<Payment> */
    private function payments(?string $branchId): Builder
    {
        return Payment::query()->join('sales', 'sales.id', '=', 'payments.sale_id')->when($branchId, fn ($q) => $q->where('sales.branch_id', $branchId));
    }

    /** @return array{0: Carbon, 1: Carbon} UTC bounds of a clinic-calendar day */
    private function day(Clinic $clinic, string $date): array
    {
        $start = Carbon::parse($date, $clinic->timezone)->startOfDay();

        return [$start->copy()->utc(), $start->copy()->endOfDay()->utc()];
    }
}
