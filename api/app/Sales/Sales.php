<?php

namespace App\Sales;

use App\Inventory\Inventory;
use App\Models\Branch;
use App\Models\Inventory\Product;
use App\Models\Owner;
use App\Models\Sales\Payment;
use App\Models\Sales\Sale;
use App\Models\Sales\SaleItem;
use App\Models\Service;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Every change to a sale goes through here (docs/inventario.md, V2), inside
 * the clinic's tenant context. Each operation locks the sale row, so totals,
 * stock and status never get out of step. Prices include IVA.
 */
final class Sales
{
    public function __construct(private readonly TenantContext $tenant, private readonly Inventory $inventory) {}

    public function open(Branch $branch, ?Owner $owner, User $actor, ?string $notes = null): Sale
    {
        return DB::transaction(function () use ($branch, $owner, $actor, $notes) {
            // Consecutive per clinic: one at a time.
            DB::select('SELECT pg_advisory_xact_lock(hashtext(?))', ['sale-number:'.$this->tenant->requireId()]);
            $number = (int) Sale::max('number') + 1;

            return Sale::create([
                'branch_id' => $branch->id,
                'number' => $number,
                'owner_id' => $owner?->id,
                'customer_snapshot' => $this->snapshot($owner),
                'notes' => $notes,
                'sold_by' => $actor->id,
                'sold_at' => now(),
            ]);
        });
    }

    /**
     * The owner's account of the day in that branch (still unpaid or partly paid),
     * or a new one: one visit = one charge, whatever was added from where.
     */
    public function accountFor(Owner $owner, Branch $branch, User $actor, string $timezone): Sale
    {
        $today = now()->setTimezone($timezone)->startOfDay()->utc();

        return Sale::where('owner_id', $owner->id)
            ->where('branch_id', $branch->id)
            ->whereIn('status', [Sale::OPEN, Sale::PARTIALLY_PAID])
            ->where('sold_at', '>=', $today)
            ->orderByDesc('sold_at')
            ->first() ?? $this->open($branch, $owner, $actor);
    }

    public function setCustomer(Sale $sale, ?Owner $owner): Sale
    {
        return $this->locked($sale, function (Sale $sale) use ($owner) {
            $this->ensureEditable($sale);
            $sale->update(['owner_id' => $owner?->id, 'customer_snapshot' => $this->snapshot($owner)]);

            return $sale;
        });
    }

    /**
     * @param  array{type: 'product'|'service'|'custom', product_id?: string|null, service_id?: string|null,
     *               description?: string|null, quantity: string|float, unit_price?: string|float|null, discount?: string|float|null, tax_rate?: string|float|null,
     *               appointment_id?: string|null, clinical_record_id?: string|null}  $data
     */
    public function addItem(Sale $sale, array $data, User $actor): SaleItem
    {
        return $this->locked($sale, function (Sale $sale) use ($data, $actor) {
            $this->ensureEditable($sale);
            $quantity = (float) $data['quantity'];

            [$description, $price, $taxRate, $product, $service] = match ($data['type']) {
                'product' => (function () use ($data) {
                    $p = Product::where('is_active', true)->findOrFail($data['product_id']);

                    return [$p->name, $data['unit_price'] ?? $p->sale_price, $p->tax_rate, $p, null];
                })(),
                'service' => (function () use ($data) {
                    $s = Service::findOrFail($data['service_id']);

                    return [$s->name, $data['unit_price'] ?? $s->price ?? 0, $s->tax_rate ?? 0, null, $s];
                })(),
                default => [trim((string) ($data['description'] ?? '')), $data['unit_price'] ?? null, $data['tax_rate'] ?? 0, null, null],
            };

            if ($description === '' || $price === null) {
                throw ValidationException::withMessages(['description' => 'Escribe la descripción y el precio del cargo.']);
            }

            $item = new SaleItem([
                'sale_id' => $sale->id,
                'item_type' => $data['type'],
                'product_id' => $product?->id,
                'service_id' => $service?->id,
                'description' => mb_substr($description, 0, 200),
                'created_by' => $actor->id,
                'appointment_id' => $data['appointment_id'] ?? null,
                'clinical_record_id' => $data['clinical_record_id'] ?? null,
            ]);
            $this->price($item, $quantity, (float) $price, (float) ($data['discount'] ?? 0), (float) $taxRate);
            $item->save();

            if ($product?->tracks_stock) {
                $this->inventory->record($product, $sale->branch, 'sale', (string) -$quantity, $actor, ['reference_type' => 'sale_item', 'reference_id' => $item->id, 'note' => "Venta N.º {$sale->number}"]);
            }

            $this->recalculate($sale);

            return $item;
        });
    }

    /** Change quantity, price or discount of a line (stock follows the quantity). */
    public function updateItem(SaleItem $item, float $quantity, float $unitPrice, float $discount, User $actor): SaleItem
    {
        return $this->locked($item->sale, function (Sale $sale) use ($item, $quantity, $unitPrice, $discount, $actor) {
            $this->ensureEditable($sale);
            $delta = round($quantity - (float) $item->quantity, 3);

            $this->price($item, $quantity, $unitPrice, $discount, (float) $item->tax_rate);
            $item->save();

            if ($delta !== 0.0 && $item->product?->tracks_stock) {
                $this->inventory->record($item->product, $sale->branch, $delta > 0 ? 'sale' : 'sale_void', (string) -$delta, $actor, ['reference_type' => 'sale_item', 'reference_id' => $item->id, 'note' => "Venta N.º {$sale->number}: cantidad cambiada"]);
            }

            $this->recalculate($sale);

            return $item;
        });
    }

    public function removeItem(SaleItem $item, User $actor): void
    {
        $this->locked($item->sale, function (Sale $sale) use ($item, $actor) {
            $this->ensureEditable($sale);
            $this->returnStock($item, $sale, $actor, "Venta N.º {$sale->number}: ítem quitado");
            $item->delete();
            $this->recalculate($sale);
        });
    }

    /** Same % off every line (e.g. 10 % for a frequent client). 0 removes it. */
    public function discountAll(Sale $sale, float $percent): Sale
    {
        if ($percent < 0 || $percent > 100) {
            throw ValidationException::withMessages(['percent' => 'El descuento debe estar entre 0 y 100 %.']);
        }

        return $this->locked($sale, function (Sale $sale) use ($percent) {
            $this->ensureEditable($sale);
            foreach ($sale->items as $item) {
                $gross = round((float) $item->quantity * (float) $item->unit_price, 2);
                $this->price($item, (float) $item->quantity, (float) $item->unit_price, round($gross * $percent / 100, 2), (float) $item->tax_rate);
                $item->save();
            }
            $this->recalculate($sale);

            return $sale;
        });
    }

    public function addPayment(Sale $sale, string $method, float $amount, ?string $reference, User $actor): Payment
    {
        return $this->locked($sale, function (Sale $sale) use ($method, $amount, $reference, $actor) {
            if ($sale->status === Sale::VOID) {
                throw ValidationException::withMessages(['amount' => 'La venta está anulada.']);
            }
            $balance = $sale->balance();
            if ($balance <= 0) {
                throw ValidationException::withMessages(['amount' => 'Esta venta no tiene saldo pendiente.']);
            }
            if (round($amount, 2) > $balance) {
                throw ValidationException::withMessages(['amount' => 'El pago supera el saldo ($'.number_format($balance, 0, ',', '.').'). Si te pagaron de más en efectivo, registra solo el saldo.']);
            }

            $payment = Payment::create([
                'sale_id' => $sale->id,
                'method' => $method,
                'amount' => round($amount, 2),
                'reference' => $reference,
                'received_by' => $actor->id,
                'paid_at' => now(),
            ]);
            $this->recalculate($sale);

            return $payment;
        });
    }

    public function voidPayment(Payment $payment, string $reason, User $actor): void
    {
        $this->locked($payment->sale, function (Sale $sale) use ($payment, $reason, $actor) {
            if ($payment->voided_at !== null) {
                throw ValidationException::withMessages(['payment' => 'Ese pago ya estaba anulado.']);
            }
            $payment->update(['voided_at' => now(), 'voided_by' => $actor->id, 'void_reason' => $reason]);
            if ($sale->status !== Sale::VOID) {
                $this->recalculate($sale);
            }
        });
    }

    /** Never deleted: products go back to stock; payments stay recorded (to refund). */
    public function void(Sale $sale, string $reason, User $actor): Sale
    {
        return $this->locked($sale, function (Sale $sale) use ($reason, $actor) {
            if ($sale->status === Sale::VOID) {
                throw ValidationException::withMessages(['reason' => 'La venta ya estaba anulada.']);
            }
            foreach ($sale->items as $item) {
                $this->returnStock($item, $sale, $actor, "Venta N.º {$sale->number} anulada");
            }
            $sale->update(['status' => Sale::VOID, 'voided_at' => now(), 'voided_by' => $actor->id, 'void_reason' => $reason]);

            return $sale;
        });
    }

    // ------------------------------------------------------------------

    /** Totals and status from the lines and the payments that count. */
    private function recalculate(Sale $sale): void
    {
        $items = SaleItem::where('sale_id', $sale->id)->get();
        $paid = (float) Payment::where('sale_id', $sale->id)->whereNull('voided_at')->sum('amount');
        $total = round((float) $items->sum('total'), 2);

        $status = match (true) {
            $paid <= 0 => Sale::OPEN,
            $paid + 0.004 < $total => Sale::PARTIALLY_PAID,
            default => Sale::PAID,
        };
        // An account with no lines yet isn't "paid".
        if ($items->isEmpty()) {
            $status = Sale::OPEN;
        }

        $sale->update([
            'subtotal' => round($items->sum(fn ($i) => (float) $i->quantity * (float) $i->unit_price), 2),
            'discount_total' => round((float) $items->sum('discount'), 2),
            'tax_total' => round((float) $items->sum('tax_amount'), 2),
            'total' => $total,
            'paid_total' => round($paid, 2),
            'status' => $status,
            'paid_at' => $status === Sale::PAID ? ($sale->paid_at ?? now()) : null,
        ]);
    }

    /** Line amounts. Prices include IVA: the tax is the part of the total that is IVA. */
    private function price(SaleItem $item, float $quantity, float $unitPrice, float $discount, float $taxRate): void
    {
        if ($quantity <= 0) {
            throw ValidationException::withMessages(['quantity' => 'La cantidad debe ser mayor que cero.']);
        }
        if ($unitPrice < 0 || $discount < 0) {
            throw ValidationException::withMessages(['unit_price' => 'El precio y el descuento no pueden ser negativos.']);
        }
        $gross = round($quantity * $unitPrice, 2);
        if ($discount > $gross) {
            throw ValidationException::withMessages(['discount' => 'El descuento no puede ser mayor que el valor del ítem.']);
        }
        $total = round($gross - $discount, 2);

        $item->fill([
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'discount' => round($discount, 2),
            'tax_rate' => $taxRate,
            'tax_amount' => $taxRate > 0 ? round($total - $total / (1 + $taxRate / 100), 2) : 0,
            'total' => $total,
        ]);
    }

    private function returnStock(SaleItem $item, Sale $sale, User $actor, string $note): void
    {
        if ($item->product?->tracks_stock) {
            $this->inventory->record($item->product, $sale->branch, 'sale_void', (string) $item->quantity, $actor, ['reference_type' => 'sale_item', 'reference_id' => $item->id, 'note' => $note]);
        }
    }

    private function ensureEditable(Sale $sale): void
    {
        if (! $sale->isEditable()) {
            throw ValidationException::withMessages(['sale' => $sale->status === Sale::VOID
                ? 'La venta está anulada.'
                : 'La venta ya está pagada: no se pueden cambiar sus ítems. Si hubo un error, anúlala y crea una nueva.']);
        }
    }

    /** @return array{name: string, document: string|null, phone: string|null}|null */
    private function snapshot(?Owner $owner): ?array
    {
        return $owner === null ? null : [
            'name' => $owner->full_name,
            'document' => $owner->document_number ? trim("{$owner->document_type} {$owner->document_number}") : null,
            'phone' => $owner->mobile_phone,
        ];
    }

    /**
     * @template T
     *
     * @param  callable(Sale): T  $fn
     * @return T
     */
    private function locked(Sale $sale, callable $fn): mixed
    {
        return DB::transaction(function () use ($sale, $fn) {
            $fresh = Sale::with(['items.product', 'branch'])->lockForUpdate()->findOrFail($sale->id);

            return $fn($fresh);
        });
    }
}
