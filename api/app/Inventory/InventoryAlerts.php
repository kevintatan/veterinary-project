<?php

namespace App\Inventory;

use App\Models\Inventory\InventoryMovement;
use App\Models\Inventory\InventoryStock;
use App\Models\Inventory\Product;
use Illuminate\Support\Collection;

/**
 * What needs attention in a branch's inventory (docs/inventario.md):
 * low or negative stock, and lots that expire soon while there is stock.
 * Runs inside the clinic's tenant context.
 */
final class InventoryAlerts
{
    /** Days before expiry when a lot starts to show up. */
    public const EXPIRY_DAYS = 60;

    /**
     * @return array{low: Collection<int, array<string, mixed>>, expiring: Collection<int, array<string, mixed>>}
     */
    public function for(string $branchId): array
    {
        $stocks = InventoryStock::where('branch_id', $branchId)->pluck('quantity', 'product_id');

        // Only products this branch handles (has ever had): a branch that never carries
        // a product shouldn't report it "agotado" every day.
        $low = Product::where('is_active', true)->where('tracks_stock', true)->whereIn('id', $stocks->keys())->orderBy('name')->get()
            ->map(fn (Product $p) => ['product' => $p, 'quantity' => (float) ($stocks[$p->id] ?? 0)])
            ->filter(fn ($row) => $row['quantity'] < 0 || ($row['product']->min_stock > 0 && $row['quantity'] <= (float) $row['product']->min_stock))
            ->map(fn ($row) => [
                'product' => $row['product']->only(['id', 'name', 'unit', 'kind']),
                'quantity' => $row['quantity'],
                'min_stock' => (float) $row['product']->min_stock,
                'status' => $row['quantity'] < 0 ? 'negative' : ($row['quantity'] == 0 ? 'out' : 'low'),
            ])
            ->values();

        $expiring = InventoryMovement::with('product:id,name,unit')
            ->where('branch_id', $branchId)
            ->whereIn('type', ['purchase', 'initial'])
            ->whereNotNull('expires_on')
            ->whereNull('lot_closed_at')
            ->where('expires_on', '<=', now()->addDays(self::EXPIRY_DAYS)->toDateString())
            ->orderBy('expires_on')
            ->get()
            // Only while the branch still has some of that product.
            ->filter(fn (InventoryMovement $m) => (float) ($stocks[$m->product_id] ?? 0) > 0)
            ->map(fn (InventoryMovement $m) => [
                'movement_id' => $m->id,
                'product' => $m->product?->only(['id', 'name', 'unit']),
                'lot_number' => $m->lot_number,
                'expires_on' => $m->expires_on->toDateString(),
                'expired' => $m->expires_on->isPast(),
                'entered_quantity' => (float) $m->quantity,
                'stock' => (float) ($stocks[$m->product_id] ?? 0),
            ])
            ->values();

        return ['low' => $low, 'expiring' => $expiring];
    }
}
