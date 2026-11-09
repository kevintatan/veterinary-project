<?php

namespace App\Inventory;

use App\Models\Branch;
use App\Models\Inventory\InventoryMovement;
use App\Models\Inventory\InventoryStock;
use App\Models\Inventory\Product;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Every change of stock goes through here (docs/inventario.md), inside the
 * clinic's tenant context. Each movement and its stock update happen in one
 * transaction with the stock row locked, so two people registering at the
 * same time can't lose a unit. Stock may go negative (flagged, not blocked).
 */
final class Inventory
{
    public function __construct(private readonly TenantContext $tenant) {}

    /** Entrada por compra: suma, guarda costo, lote y vencimiento; actualiza el costo del producto. */
    public function purchase(Product $product, Branch $branch, string $quantity, ?User $actor, array $extra = []): InventoryMovement
    {
        $this->ensurePositive($quantity);

        return DB::transaction(function () use ($product, $branch, $quantity, $actor, $extra) {
            $movement = $this->record($product, $branch, 'purchase', $quantity, $actor, $extra);

            if (isset($extra['unit_cost']) && $extra['unit_cost'] !== null) {
                $product->update(['cost_price' => $extra['unit_cost']]); // last purchase cost
            }

            return $movement;
        });
    }

    /** Existencia inicial (al crear o importar productos). */
    public function initial(Product $product, Branch $branch, string $quantity, ?User $actor, array $extra = []): ?InventoryMovement
    {
        if ((float) $quantity === 0.0) {
            return null;
        }

        return DB::transaction(fn () => $this->record($product, $branch, 'initial', $quantity, $actor, $extra));
    }

    /**
     * Ajuste. reason "count": $quantity is what was counted (sets the stock);
     * any other reason: $quantity is how much to take out.
     */
    public function adjust(Product $product, Branch $branch, string $reason, string $quantity, ?User $actor, array $extra = []): InventoryMovement
    {
        return DB::transaction(function () use ($product, $branch, $reason, $quantity, $actor, $extra) {
            if ($reason === 'count') {
                if ((float) $quantity < 0) {
                    throw ValidationException::withMessages(['quantity' => 'El conteo no puede ser negativo.']);
                }
                $delta = round((float) $quantity - (float) $this->lockedStock($product, $branch)->quantity, 3);
                if ($delta === 0.0) {
                    throw ValidationException::withMessages(['quantity' => 'El conteo es igual a la existencia actual: no hay nada que ajustar.']);
                }
            } else {
                $this->ensurePositive($quantity);
                $delta = -1 * (float) $quantity;
            }

            $movement = $this->record($product, $branch, 'adjustment', (string) $delta, $actor, [...$extra, 'reason' => $reason]);

            // Taking out an expired lot closes its expiry alert.
            if ($reason === 'expired' && ! empty($extra['lot_movement_id'])) {
                $this->closeLot($extra['lot_movement_id']);
            }

            return $movement;
        });
    }

    /** Traslado entre sedes: una salida y una entrada enlazadas. */
    public function transfer(Product $product, Branch $from, Branch $to, string $quantity, ?User $actor, ?string $note = null): array
    {
        $this->ensurePositive($quantity);
        if ($from->id === $to->id) {
            throw ValidationException::withMessages(['to_branch_id' => 'Elige una sede distinta a la de origen.']);
        }

        return DB::transaction(function () use ($product, $from, $to, $quantity, $actor, $note) {
            $group = (string) Str::uuid7();
            $extra = ['transfer_group' => $group, 'note' => $note];

            // Lock both rows always in the same order (no deadlocks between opposite transfers).
            collect([$from, $to])->sortBy('id')->each(fn (Branch $b) => $this->lockedStock($product, $b));

            return [
                $this->record($product, $from, 'transfer_out', '-'.$quantity, $actor, $extra),
                $this->record($product, $to, 'transfer_in', $quantity, $actor, $extra),
            ];
        });
    }

    /** "Ya revisé este lote": hides its expiry alert. */
    public function closeLot(string $movementId): void
    {
        InventoryMovement::whereKey($movementId)->whereIn('type', ['purchase', 'initial'])->whereNull('lot_closed_at')
            ->update(['lot_closed_at' => now()]);
    }

    /**
     * The only place that writes a movement and moves the stock.
     *
     * @param  array<string, mixed>  $extra
     */
    public function record(Product $product, Branch $branch, string $type, string $quantity, ?User $actor, array $extra = []): InventoryMovement
    {
        if (! $product->tracks_stock) {
            throw ValidationException::withMessages(['product_id' => "«{$product->name}» no controla existencias."]);
        }

        return DB::transaction(function () use ($product, $branch, $type, $quantity, $actor, $extra) {
            $stock = $this->lockedStock($product, $branch);
            $balance = round((float) $stock->quantity + (float) $quantity, 3);
            $stock->update(['quantity' => $balance]);

            return InventoryMovement::create([
                'branch_id' => $branch->id,
                'product_id' => $product->id,
                'type' => $type,
                'quantity' => $quantity,
                'balance_after' => $balance,
                'unit_cost' => $extra['unit_cost'] ?? null,
                'lot_number' => $extra['lot_number'] ?? null,
                'expires_on' => $extra['expires_on'] ?? null,
                'reason' => $extra['reason'] ?? null,
                'note' => $extra['note'] ?? null,
                'transfer_group' => $extra['transfer_group'] ?? null,
                'reference_type' => $extra['reference_type'] ?? null,
                'reference_id' => $extra['reference_id'] ?? null,
                'user_id' => $actor?->id,
                'occurred_at' => $extra['occurred_at'] ?? now(),
                'created_at' => now(),
            ]);
        });
    }

    /** The stock row of a product in a branch, created at 0 if missing, locked until the transaction ends. */
    private function lockedStock(Product $product, Branch $branch): InventoryStock
    {
        DB::statement(
            'INSERT INTO inventory_stocks (id, clinic_id, branch_id, product_id, quantity, created_at, updated_at)
             VALUES (?, ?, ?, ?, 0, now(), now()) ON CONFLICT (branch_id, product_id) DO NOTHING',
            [(string) Str::uuid7(), $this->tenant->requireId(), $branch->id, $product->id],
        );

        return InventoryStock::where('branch_id', $branch->id)->where('product_id', $product->id)->lockForUpdate()->firstOrFail();
    }

    private function ensurePositive(string $quantity): void
    {
        if (! is_numeric($quantity) || (float) $quantity <= 0) {
            throw ValidationException::withMessages(['quantity' => 'La cantidad debe ser mayor que cero.']);
        }
    }
}
