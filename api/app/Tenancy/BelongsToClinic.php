<?php

namespace App\Tenancy;

use App\Models\Clinic;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Mandatory for every tenant model (docs/database.md D2):
 * scopes queries to the clinic in context and stamps clinic_id on create.
 *
 * @mixin Model
 */
trait BelongsToClinic
{
    public static function bootBelongsToClinic(): void
    {
        static::addGlobalScope(new ClinicScope);

        static::creating(function (Model $model): void {
            $context = app(TenantContext::class);

            if ($context->isBypassed() && $model->getAttribute('clinic_id') !== null) {
                return;
            }

            $clinicId = $context->requireId();
            $current = $model->getAttribute('clinic_id');

            if ($current === null) {
                $model->setAttribute('clinic_id', $clinicId);
            } elseif ($current !== $clinicId) {
                throw TenancyViolation::crossTenantWrite($model::class);
            }
        });

        static::updating(function (Model $model): void {
            if ($model->isDirty('clinic_id')) {
                throw TenancyViolation::clinicChange($model::class);
            }
        });
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }
}
