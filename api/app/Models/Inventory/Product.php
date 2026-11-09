<?php

namespace App\Models\Inventory;

use App\Models\Concerns\StoresDatesInUtc;
use App\Tenancy\BelongsToClinic;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Something the clinic sells or uses (docs/inventario.md). Prices include IVA.
 *
 * @property string $id
 * @property string $name
 * @property string $kind
 * @property string $unit
 * @property bool $tracks_stock
 * @property string $min_stock
 */
class Product extends Model
{
    use BelongsToClinic, HasUuids, LogsActivity;
    use StoresDatesInUtc;

    public const KINDS = [
        'product' => 'Producto',
        'medication' => 'Medicamento',
        'vaccine' => 'Vacuna',
        'supply' => 'Insumo',
    ];

    public const UNITS = [
        'unit' => 'Unidad',
        'box' => 'Caja',
        'ml' => 'ml',
        'tablet' => 'Tableta',
        'dose' => 'Dosis',
        'g' => 'g',
        'kg' => 'kg',
        'bottle' => 'Frasco',
        'pipette' => 'Pipeta',
    ];

    protected $fillable = ['name', 'sku', 'kind', 'unit', 'sale_price', 'cost_price', 'tax_rate', 'tracks_stock', 'min_stock', 'is_active'];

    /** "  VACUNA Múltiple " and "vacuna múltiple" are the same product (the DB can't lowercase "Ú"). */
    public static function nameKey(string $name): string
    {
        return mb_strtolower(preg_replace('/\s+/u', ' ', trim($name)));
    }

    protected static function booted(): void
    {
        static::saving(function (Product $product): void {
            $product->name = trim($product->name);
            $product->name_key = self::nameKey($product->name);
        });
    }

    protected function casts(): array
    {
        return [
            'sale_price' => 'decimal:2',
            'cost_price' => 'decimal:2',
            'tax_rate' => 'decimal:2',
            'min_stock' => 'decimal:3',
            'tracks_stock' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(InventoryStock::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }
}
