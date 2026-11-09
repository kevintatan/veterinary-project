<?php

namespace App\Tenancy;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * For mixed catalogs (docs/database.md D9). Global rows (clinic_id NULL)
 * can only be written under an explicit platform bypass (seeders);
 * anything a clinic creates belongs to that clinic.
 *
 * @mixin Model
 */
trait IsClinicCatalog
{
    public static function bootIsClinicCatalog(): void
    {
        static::addGlobalScope(new CatalogScope);

        static::creating(function (Model $model): void {
            $context = app(TenantContext::class);

            if ($context->isBypassed()) {
                return; // seeding global rows
            }

            $clinicId = $context->requireId();
            $current = $model->getAttribute('clinic_id');

            if ($current === null) {
                $model->setAttribute('clinic_id', $clinicId);
            } elseif ($current !== $clinicId) {
                throw TenancyViolation::crossTenantWrite($model::class);
            }
        });

        $guardGlobal = function (Model $model): void {
            $context = app(TenantContext::class);

            if ($context->isBypassed()) {
                return;
            }

            if ($model->getOriginal('clinic_id') === null) {
                throw new TenancyViolation('Global catalog rows are read-only for clinics.');
            }

            if ($model->isDirty('clinic_id')) {
                throw TenancyViolation::clinicChange($model::class);
            }
        };

        static::updating($guardGlobal);
        static::deleting($guardGlobal);
    }

    public function isGlobal(): bool
    {
        return $this->getAttribute('clinic_id') === null;
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where($this->qualifyColumn('is_active'), true);
    }
}
