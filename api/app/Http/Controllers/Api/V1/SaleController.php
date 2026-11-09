<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Clinic;
use App\Models\Clinical\ClinicalRecord;
use App\Models\Inventory\InventoryStock;
use App\Models\Inventory\Product;
use App\Models\Owner;
use App\Models\Sales\Payment;
use App\Models\Sales\Sale;
use App\Models\Sales\SaleItem;
use App\Models\Service;
use App\Models\User;
use App\Sales\CareCharges;
use App\Sales\ReceiptPdf;
use App\Sales\Sales;
use App\Support\ClinicTime;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

/** Sales and payments (docs/inventario.md, V2). Not electronic invoices. */
class SaleController extends Controller
{
    public function __construct(private readonly Sales $sales) {}

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'status' => ['nullable', Rule::in(['pending', 'paid', 'void', 'all'])],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
            'q' => ['nullable', 'string', 'max:100'],
            'branch_id' => ['nullable', 'uuid'],
        ]);
        $clinic = $this->clinic($request);
        $status = $data['status'] ?? 'all';

        $sales = Sale::with(['seller:id,name', 'branch:id,name'])
            ->when($status === 'pending', fn ($q) => $q->whereIn('status', [Sale::OPEN, Sale::PARTIALLY_PAID]))
            ->when(in_array($status, ['paid', 'void'], true), fn ($q) => $q->where('status', $status))
            ->when($data['branch_id'] ?? null, fn ($q, $b) => $q->where('branch_id', $b))
            ->when($data['from'] ?? null, fn ($q, $from) => $q->where('sold_at', '>=', ClinicTime::parse($from.'T00:00', $clinic)))
            ->when($data['to'] ?? null, fn ($q, $to) => $q->where('sold_at', '<', ClinicTime::parse($to.'T00:00', $clinic)->addDay()))
            ->when($data['q'] ?? null, function ($q, $term) {
                $term = trim($term);
                ctype_digit(ltrim($term, '#')) ? $q->where('number', (int) ltrim($term, '#'))
                    : $q->where('customer_snapshot->name', 'ilike', '%'.addcslashes($term, '%_\\').'%');
            })
            ->orderByDesc('sold_at')->orderByDesc('number')
            ->paginate(30);

        return response()->json([
            'data' => $sales->getCollection()->map(fn (Sale $s) => $this->summary($s, $clinic)),
            'meta' => ['current_page' => $sales->currentPage(), 'last_page' => $sales->lastPage(), 'total' => $sales->total()],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'branch_id' => ['required', 'uuid'],
            'owner_id' => ['nullable', 'uuid'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $sale = $this->sales->open(
            Branch::where('is_active', true)->findOrFail($data['branch_id']),
            isset($data['owner_id']) ? Owner::findOrFail($data['owner_id']) : null,
            $request->user(),
            $data['notes'] ?? null,
        );

        return $this->show($request, $sale)->setStatusCode(201);
    }

    public function show(Request $request, Sale $sale): JsonResponse
    {
        $clinic = $this->clinic($request);
        $sale->load(['items.product:id,unit,tracks_stock', 'items.clinicalRecord:id,type,pet_id', 'items.clinicalRecord.pet:id,name', 'items.appointment:id,starts_at', 'payments.receiver:id,name', 'owner:id,full_name,mobile_phone', 'seller:id,name', 'branch:id,name']);
        $voidedBy = $sale->voided_by ? User::whereKey($sale->voided_by)->value('name') : null;

        return response()->json(['data' => [
            ...$this->summary($sale, $clinic),
            'subtotal' => (float) $sale->subtotal,
            'discount_total' => (float) $sale->discount_total,
            'tax_total' => (float) $sale->tax_total,
            'notes' => $sale->notes,
            'owner' => $sale->owner?->only(['id', 'full_name', 'mobile_phone']),
            'void_reason' => $sale->void_reason,
            'voided_by' => $voidedBy,
            'voided_at' => ClinicTime::iso($sale->voided_at, $clinic),
            'editable' => $sale->isEditable(),
            'items' => $sale->items->map(fn (SaleItem $i) => [
                'id' => $i->id,
                'type' => $i->item_type,
                'description' => $i->description,
                'unit' => $i->product?->unit,
                'quantity' => (float) $i->quantity,
                'unit_price' => (float) $i->unit_price,
                'discount' => (float) $i->discount,
                'tax_rate' => (float) $i->tax_rate,
                'tax_amount' => (float) $i->tax_amount,
                'total' => (float) $i->total,
                'moves_stock' => (bool) $i->product?->tracks_stock,
                // Where it came from (V3): the appointment charged or the clinical record that used it.
                'origin' => match (true) {
                    $i->clinicalRecord !== null => ['kind' => 'clinical_record', 'label' => (ClinicalRecord::TYPES[$i->clinicalRecord->type] ?? 'Atención').' · '.$i->clinicalRecord->pet?->name, 'pet_id' => $i->clinicalRecord->pet_id],
                    $i->appointment !== null => ['kind' => 'appointment', 'label' => 'Cita del '.$i->appointment->starts_at->copy()->setTimezone($clinic->timezone)->translatedFormat('j \d\e M, g:i a'), 'pet_id' => null],
                    default => null,
                },
            ]),
            'payments' => $sale->payments->map(fn (Payment $p) => [
                'id' => $p->id,
                'method' => ['value' => $p->method, 'label' => Payment::METHODS[$p->method]],
                'amount' => (float) $p->amount,
                'reference' => $p->reference,
                'received_by' => $p->receiver?->name,
                'paid_at' => ClinicTime::iso($p->paid_at, $clinic),
                'voided' => $p->voided_at !== null,
                'void_reason' => $p->void_reason,
            ]),
        ]]);
    }

    public function update(Request $request, Sale $sale): JsonResponse
    {
        $data = $request->validate(['owner_id' => ['nullable', 'uuid']]);
        $this->sales->setCustomer($sale, isset($data['owner_id']) ? Owner::findOrFail($data['owner_id']) : null);

        return $this->show($request, $sale->refresh());
    }

    public function addItem(Request $request, Sale $sale): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(['product', 'service', 'custom'])],
            'product_id' => ['required_if:type,product', 'nullable', 'uuid'],
            'service_id' => ['required_if:type,service', 'nullable', 'uuid'],
            'description' => ['required_if:type,custom', 'nullable', 'string', 'max:200'],
            'quantity' => ['required', 'numeric', 'gt:0', 'max:99999'],
            'unit_price' => ['required_if:type,custom', 'nullable', 'numeric', 'min:0', 'max:999999999'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'tax_rate' => ['nullable', Rule::in([0, 5, 19])],
        ], [], ['quantity' => 'cantidad', 'unit_price' => 'precio', 'description' => 'descripción', 'discount' => 'descuento']);

        $this->sales->addItem($sale, $data, $request->user());

        return $this->show($request, $sale->refresh());
    }

    public function updateItem(Request $request, SaleItem $item): JsonResponse
    {
        $data = $request->validate([
            'quantity' => ['required', 'numeric', 'gt:0', 'max:99999'],
            'unit_price' => ['required', 'numeric', 'min:0', 'max:999999999'],
            'discount' => ['nullable', 'numeric', 'min:0'],
        ], [], ['quantity' => 'cantidad', 'unit_price' => 'precio', 'discount' => 'descuento']);

        $this->sales->updateItem($item, (float) $data['quantity'], (float) $data['unit_price'], (float) ($data['discount'] ?? 0), $request->user());

        return $this->show($request, $item->sale->refresh());
    }

    public function removeItem(Request $request, SaleItem $item): JsonResponse
    {
        $sale = $item->sale;
        $this->sales->removeItem($item, $request->user());

        return $this->show($request, $sale->refresh());
    }

    public function discount(Request $request, Sale $sale): JsonResponse
    {
        $data = $request->validate(['percent' => ['required', 'numeric', 'min:0', 'max:100']], [], ['percent' => 'descuento']);
        $this->sales->discountAll($sale, (float) $data['percent']);

        return $this->show($request, $sale->refresh());
    }

    public function addPayment(Request $request, Sale $sale): JsonResponse
    {
        $data = $request->validate([
            'method' => ['required', Rule::in(array_keys(Payment::METHODS))],
            'amount' => ['required', 'numeric', 'gt:0', 'max:999999999'],
            'reference' => ['nullable', 'string', 'max:100'],
        ], [], ['method' => 'medio de pago', 'amount' => 'valor', 'reference' => 'referencia']);

        $this->sales->addPayment($sale, $data['method'], (float) $data['amount'], $data['reference'] ?? null, $request->user());

        return $this->show($request, $sale->refresh());
    }

    public function voidPayment(Request $request, Payment $payment): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:255']], [], ['reason' => 'motivo']);
        $this->sales->voidPayment($payment, $data['reason'], $request->user());

        return $this->show($request, $payment->sale->refresh());
    }

    public function void(Request $request, Sale $sale): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:255']], [], ['reason' => 'motivo']);
        $this->sales->void($sale, $data['reason'], $request->user());

        return $this->show($request, $sale->refresh());
    }

    public function receipt(Request $request, Sale $sale, ReceiptPdf $pdf): Response
    {
        return response($pdf->bytes($sale), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "inline; filename=\"comprobante-{$sale->number}.pdf\"",
            'Cache-Control' => 'private, no-store',
        ]);
    }

    /** "Cobrar" from an appointment: its sale if already charged, else the owner's account of the day with the service. */
    public function chargeAppointment(Request $request, Appointment $appointment, CareCharges $charges): JsonResponse
    {
        $sale = $charges->chargeAppointment($appointment, $request->user(), $this->clinic($request));

        return response()->json(['data' => ['sale_id' => $sale->id, 'number' => $sale->number]]);
    }

    /** What can be added to a sale: products (with stock in the branch) and services. */
    public function sellables(Request $request): JsonResponse
    {
        $data = $request->validate(['q' => ['nullable', 'string', 'max:100'], 'branch_id' => ['required', 'uuid']]);
        $term = trim($data['q'] ?? '');
        $like = '%'.addcslashes($term, '%_\\').'%';
        $stocks = InventoryStock::where('branch_id', $data['branch_id'])->pluck('quantity', 'product_id');

        $products = Product::where('is_active', true)
            ->when($term !== '', fn ($q) => $q->where(fn ($q) => $q->where('name', 'ilike', $like)->orWhere('sku', 'ilike', addcslashes($term, '%_\\').'%')))
            ->orderBy('name')->limit(15)->get()
            ->map(fn (Product $p) => [
                'type' => 'product', 'id' => $p->id, 'name' => $p->name, 'detail' => Product::KINDS[$p->kind].($p->sku ? " · {$p->sku}" : ''),
                'price' => (float) $p->sale_price, 'unit' => $p->unit,
                'stock' => $p->tracks_stock ? (float) ($stocks[$p->id] ?? 0) : null,
            ]);
        $services = Service::where('is_active', true)
            ->when($term !== '', fn ($q) => $q->where('name', 'ilike', $like))
            ->orderBy('name')->limit(10)->get()
            ->map(fn (Service $s) => ['type' => 'service', 'id' => $s->id, 'name' => $s->name, 'detail' => 'Servicio', 'price' => (float) ($s->price ?? 0), 'unit' => null, 'stock' => null]);

        return response()->json(['data' => $services->concat($products)->values()]);
    }

    /** The owner's account: what they owe and their sales. */
    public function ownerAccount(Request $request, Owner $owner): JsonResponse
    {
        $clinic = $this->clinic($request);
        $sales = Sale::where('owner_id', $owner->id)->orderByDesc('sold_at')->limit(50)->get();
        $pending = $sales->whereIn('status', [Sale::OPEN, Sale::PARTIALLY_PAID]);

        return response()->json(['data' => [
            'balance' => round($pending->sum(fn (Sale $s) => $s->balance()), 2),
            'pending_count' => $pending->count(),
            'sales' => $sales->map(fn (Sale $s) => $this->summary($s, $clinic)),
        ]]);
    }

    /** @return array<string, mixed> */
    private function summary(Sale $s, Clinic $clinic): array
    {
        return [
            'id' => $s->id,
            'number' => $s->number,
            'status' => ['value' => $s->status, 'label' => Sale::STATUSES[$s->status]],
            'customer' => $s->customer_snapshot,
            'owner_id' => $s->owner_id,
            'total' => (float) $s->total,
            'paid_total' => (float) $s->paid_total,
            'balance' => $s->status === Sale::VOID ? 0 : $s->balance(),
            'sold_at' => ClinicTime::iso($s->sold_at, $clinic),
            'seller' => $s->relationLoaded('seller') ? $s->seller?->name : null,
            'branch' => $s->relationLoaded('branch') ? $s->branch?->only(['id', 'name']) : null,
        ];
    }

    private function clinic(Request $request): Clinic
    {
        return $request->attributes->get('clinic');
    }
}
