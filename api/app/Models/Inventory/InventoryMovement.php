<?php

namespace App\Models\Inventory;

use App\Models\Branch;
use App\Models\Concerns\StoresDatesInUtc;
use App\Models\User;
use App\Tenancy\BelongsToClinic;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line of the inventory ledger (append-only, docs/inventario.md).
 * Created only by App\Inventory\RecordMovement.
 *
 * @property string $type
 * @property string $quantity
 * @property string $balance_after
 */
class InventoryMovement extends Model
{
    use BelongsToClinic, HasUuids;
    use StoresDatesInUtc;

    public const UPDATED_AT = null;

    public const TYPES = [
        'initial' => 'Existencia inicial',
        'purchase' => 'Entrada (compra)',
        'adjustment' => 'Ajuste',
        'transfer_out' => 'Traslado (sale)',
        'transfer_in' => 'Traslado (entra)',
        'sale' => 'Venta',
        'sale_void' => 'Venta anulada',
        'clinical_use' => 'Uso en atención',
    ];

    public const REASONS = [
        'count' => 'Conteo físico',
        'damage' => 'Daño',
        'loss' => 'Pérdida',
        'expired' => 'Vencido',
        'other' => 'Otro',
    ];

    protected $fillable = [
        'branch_id', 'product_id', 'type', 'quantity', 'balance_after', 'unit_cost', 'lot_number', 'expires_on',
        'reason', 'note', 'transfer_group', 'reference_type', 'reference_id', 'user_id', 'occurred_at', 'lot_closed_at',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'balance_after' => 'decimal:3',
            'unit_cost' => 'decimal:2',
            'expires_on' => 'date',
            'occurred_at' => 'datetime',
            'lot_closed_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
