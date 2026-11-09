<?php

namespace App\Tenancy;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Restricts every query to the clinic in context. Fails closed: without a
 * clinic it throws instead of returning rows from every clinic.
 */
final class ClinicScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $context = app(TenantContext::class);

        if ($context->isBypassed()) {
            return;
        }

        $builder->where($model->qualifyColumn('clinic_id'), $context->requireId());
    }
}
