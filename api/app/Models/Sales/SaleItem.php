<?php

namespace App\Models\Sales;

use App\Models\Appointment;
use App\Models\Clinical\ClinicalRecord;
use App\Models\Concerns\StoresDatesInUtc;
use App\Models\Inventory\Product;
use App\Models\Service;
use App\Tenancy\BelongsToClinic;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line of a sale, with a snapshot of what was sold and at what price.
 *
 * @property string $item_type
 * @property string $quantity
 * @property string $total
 */
class SaleItem extends Model
{
    use BelongsToClinic, HasUuids;
    use StoresDatesInUtc;

    protected $fillable = ['sale_id', 'item_type', 'product_id', 'service_id', 'description', 'quantity', 'unit_price', 'discount', 'tax_rate', 'tax_amount', 'total', 'created_by', 'appointment_id', 'clinical_record_id'];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'unit_price' => 'decimal:2',
            'discount' => 'decimal:2',
            'tax_rate' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function clinicalRecord(): BelongsTo
    {
        return $this->belongsTo(ClinicalRecord::class);
    }
}
