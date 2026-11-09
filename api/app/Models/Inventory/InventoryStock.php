<?php

namespace App\Models\Inventory;

use App\Models\Branch;
use App\Models\Concerns\StoresDatesInUtc;
use App\Tenancy\BelongsToClinic;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Current quantity of a product in a branch. Only RecordMovement changes it,
 * in the same transaction as the movement (docs/inventario.md).
 *
 * @property string $quantity
 */
class InventoryStock extends Model
{
    use BelongsToClinic, HasUuids;
    use StoresDatesInUtc;

    protected $fillable = ['branch_id', 'product_id', 'quantity'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:3'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
