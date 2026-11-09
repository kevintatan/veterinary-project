<?php

namespace App\Models\Sales;

use App\Models\Branch;
use App\Models\Concerns\StoresDatesInUtc;
use App\Models\Owner;
use App\Models\User;
use App\Tenancy\BelongsToClinic;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A customer's account at the clinic (docs/inventario.md, V2). Not an
 * electronic invoice. Only App\Sales\Sales changes it.
 *
 * @property string $id
 * @property int $number
 * @property string $status
 * @property string $total
 * @property string $paid_total
 */
class Sale extends Model
{
    use BelongsToClinic, HasUuids;
    use StoresDatesInUtc;

    public const OPEN = 'open';

    public const PARTIALLY_PAID = 'partially_paid';

    public const PAID = 'paid';

    public const VOID = 'void';

    public const STATUSES = [
        self::OPEN => 'Pendiente',
        self::PARTIALLY_PAID => 'Abonada',
        self::PAID => 'Pagada',
        self::VOID => 'Anulada',
    ];

    protected $fillable = [
        'branch_id', 'number', 'owner_id', 'status', 'subtotal', 'discount_total', 'tax_total', 'total', 'paid_total',
        'customer_snapshot', 'notes', 'sold_by', 'sold_at', 'paid_at', 'voided_at', 'voided_by', 'void_reason',
    ];

    protected $attributes = ['status' => self::OPEN];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'tax_total' => 'decimal:2',
            'total' => 'decimal:2',
            'paid_total' => 'decimal:2',
            'customer_snapshot' => 'array',
            'sold_at' => 'datetime',
            'paid_at' => 'datetime',
            'voided_at' => 'datetime',
        ];
    }

    public function balance(): float
    {
        return round((float) $this->total - (float) $this->paid_total, 2);
    }

    /** Items can change while nothing closed it (fully paid or voided). */
    public function isEditable(): bool
    {
        return in_array($this->status, [self::OPEN, self::PARTIALLY_PAID], true);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class)->orderBy('created_at')->orderBy('id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->orderBy('paid_at')->orderBy('id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(Owner::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sold_by');
    }
}
