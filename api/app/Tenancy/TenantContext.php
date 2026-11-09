<?php

namespace App\Tenancy;

use Illuminate\Support\Facades\Log;

/**
 * The clinic (tenant) the current request or job operates on.
 *
 * Single source of truth: the Eloquent global scope, the automatic clinic_id
 * assignment and Spatie's permission team all read from here.
 * Bound as a *scoped* instance, so it is flushed between queue jobs.
 */
final class TenantContext
{
    private ?string $clinicId = null;

    private int $bypassDepth = 0;

    public function set(string $clinicId): void
    {
        $this->clinicId = $clinicId;
    }

    public function forget(): void
    {
        $this->clinicId = null;
    }

    public function id(): ?string
    {
        return $this->clinicId;
    }

    public function has(): bool
    {
        return $this->clinicId !== null;
    }

    /**
     * @throws TenancyViolation when no clinic is in context
     */
    public function requireId(): string
    {
        return $this->clinicId ?? throw TenancyViolation::missingContext();
    }

    /**
     * Run a callback inside a clinic, restoring the previous context afterwards.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public function runAs(string $clinicId, callable $callback): mixed
    {
        $previous = $this->clinicId;
        $this->clinicId = $clinicId;

        try {
            return $callback();
        } finally {
            $this->clinicId = $previous;
        }
    }

    /**
     * Platform-level work that must see every clinic (e.g. expiring trials).
     * Always explicit and logged: it is the only way to skip isolation.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public function withoutTenancy(string $reason, callable $callback): mixed
    {
        Log::notice('tenancy.bypass', ['reason' => $reason]);

        $this->bypassDepth++;

        try {
            return $callback();
        } finally {
            $this->bypassDepth--;
        }
    }

    public function isBypassed(): bool
    {
        return $this->bypassDepth > 0;
    }
}
