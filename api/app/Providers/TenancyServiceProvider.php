<?php

namespace App\Providers;

use App\Models\PersonalAccessToken;
use App\Tenancy\TenantContext;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;

class TenancyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Scoped: flushed by Laravel between queue jobs (and Octane requests).
        $this->app->scoped(TenantContext::class);
    }

    public function boot(): void
    {
        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);

        $this->registerBlueprintMacros();
    }

    /**
     * Schema helpers so no tenant table can forget its clinic_id
     * or its composite foreign keys (docs/database.md D2/D3).
     */
    private function registerBlueprintMacros(): void
    {
        // clinic_id uuid NOT NULL + FK to clinics + index.
        Blueprint::macro('clinic', function (): void {
            /** @var Blueprint $this */
            $this->foreignUuid('clinic_id')->index()->constrained('clinics')->restrictOnDelete();
        });

        // unique (clinic_id, id): lets children reference this table with a composite FK.
        Blueprint::macro('tenantKey', function (): void {
            /** @var Blueprint $this */
            $this->unique(['clinic_id', 'id']);
        });

        // {column} uuid + FK (clinic_id, {column}) -> {table}(clinic_id, id).
        // The database itself rejects references to rows of another clinic.
        Blueprint::macro('tenantForeign', function (string $column, string $table, bool $nullable = false, string $onDelete = 'restrict'): void {
            /** @var Blueprint $this */
            $this->uuid($column)->nullable($nullable)->index();
            $this->foreign(['clinic_id', $column])
                ->references(['clinic_id', 'id'])
                ->on($table)
                ->onDelete($onDelete);
        });
    }
}
