<?php

namespace App\Tenancy;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Mixed catalogs: the clinic sees global rows (clinic_id NULL) plus its own.
 * Fails closed like ClinicScope.
 */
final class CatalogScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $context = app(TenantContext::class);

        if ($context->isBypassed()) {
            return;
        }

        $clinicId = $context->requireId();
        $column = $model->qualifyColumn('clinic_id');

        $builder->where(fn (Builder $q) => $q->whereNull($column)->orWhere($column, $clinicId));
    }
}
