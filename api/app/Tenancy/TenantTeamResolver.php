<?php

namespace App\Tenancy;

use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Contracts\PermissionsTeamResolver;

/**
 * Makes Spatie's permission "team" always equal the clinic in context,
 * so roles can never be evaluated against a different clinic.
 */
final class TenantTeamResolver implements PermissionsTeamResolver
{
    public function setPermissionsTeamId(int|string|Model|null $id): void
    {
        $context = app(TenantContext::class);

        if ($id instanceof Model) {
            $id = $id->getKey();
        }

        if ($id === null) {
            $context->forget();

            return;
        }

        // Switching clinics must go through TenantContext explicitly, never silently via Spatie.
        if ($context->has() && $context->id() !== (string) $id) {
            throw new TenancyViolation('Spatie tried to switch the permission team to another clinic.');
        }

        $context->set((string) $id);
    }

    public function getPermissionsTeamId(): int|string|null
    {
        return app(TenantContext::class)->id();
    }
}
