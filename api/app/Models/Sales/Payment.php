<?php

namespace App\Models\Sales;

use App\Models\Concerns\StoresDatesInUtc;
use App\Models\User;
use App\Tenancy\BelongsToClinic;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Money received for a sale. Voided with a reason, never deleted.
 *
 * @property string $method
 * @property string $amount
 */
class Payment extends Model
{
    use BelongsToClinic, HasUuids;
    use StoresDatesInUtc;

    public const METHODS = [
        'cash' => 'Efectivo',
        'card' => 'Tarjeta',
        'transfer' => 'Transferencia',
        'nequi' => 'Nequi',
        'daviplata' => 'Daviplata',
        'other' => 'Otro',
    ];

    protected $fillable = ['sale_id', 'method', 'amount', 'reference', 'received_by', 'paid_at', 'voided_at', 'voided_by', 'void_reason'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'paid_at' => 'datetime', 'voided_at' => 'datetime'];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }
}
