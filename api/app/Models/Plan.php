<?php

namespace App\Models;

use App\Models\Concerns\StoresDatesInUtc;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Commercial plan (platform table, shared by every clinic).
 *
 * @property string $id
 * @property string $code
 * @property array<string, bool> $features
 * @property array<string, int|null> $limits
 */
class Plan extends Model
{
    use HasUuids;
    use StoresDatesInUtc;

    public const PRO = 'pro';

    protected $fillable = ['code', 'name', 'price_monthly', 'price_yearly', 'features', 'limits', 'is_active', 'sort'];

    protected function casts(): array
    {
        return [
            'price_monthly' => 'decimal:2',
            'price_yearly' => 'decimal:2',
            'features' => 'array',
            'limits' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /** What each feature means for a clinic; `soon` = not built yet. Shown on the landing and in Plan y facturación. */
    public const FEATURE_LABELS = [
        'whatsapp_bot' => ['label' => 'Recordatorios y confirmación de citas por WhatsApp', 'soon' => false],
        'online_booking' => ['label' => 'Reserva de citas en línea', 'soon' => false],
        'whatsapp_inbox' => ['label' => 'Chat de WhatsApp con los tutores', 'soon' => false],
        'advanced_reports' => ['label' => 'Reportes de ventas y exportación a Excel', 'soon' => false],
        'invoicing' => ['label' => 'Facturación electrónica DIAN', 'soon' => true],
    ];

    /**
     * Active plans as shown to clinics (public: no internal ids).
     *
     * @return list<array<string, mixed>>
     */
    public static function catalog(): array
    {
        return self::where('is_active', true)->orderBy('sort')->get()->map(fn (Plan $p) => [
            'code' => $p->code,
            'name' => $p->name,
            'price_monthly' => (int) $p->price_monthly,
            'features' => collect(self::FEATURE_LABELS)->map(fn ($f, $key) => [...$f, 'key' => $key, 'included' => $p->hasFeature($key)])->values()->all(),
            'limits' => $p->limits,
        ])->all();
    }

    public function hasFeature(string $feature): bool
    {
        return (bool) ($this->features[$feature] ?? false);
    }
}
