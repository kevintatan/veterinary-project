<?php

namespace App\Tenancy;

use LogicException;

/**
 * A programming error that could leak or corrupt data across clinics.
 * Never caught to "recover": it must surface in tests and logs.
 */
final class TenancyViolation extends LogicException
{
    public function __construct(string $message)
    {
        parent::__construct($message);
    }

    public static function missingContext(): self
    {
        return new self('Tenant-scoped model used without a clinic in context.');
    }

    public static function crossTenantWrite(string $model): self
    {
        return new self("Attempt to create {$model} for a clinic other than the one in context.");
    }

    public static function clinicChange(string $model): self
    {
        return new self("clinic_id of {$model} cannot be changed.");
    }
}
