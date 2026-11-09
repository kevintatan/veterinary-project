<?php

namespace App\Tenancy\Jobs;

use App\Tenancy\TenantContext;

/**
 * For queued jobs that touch tenant data (docs/database.md §3, safeguard 5).
 * Captures the clinic in context when the job is created and restores it,
 * and only it, while the job runs. Call $this->captureClinic() in the constructor.
 */
trait TenantAware
{
    public ?string $clinicId = null;

    protected function captureClinic(): void
    {
        $this->clinicId = app(TenantContext::class)->requireId();
    }

    /**
     * @return list<object>
     */
    public function middleware(): array
    {
        return [new RunInClinic];
    }
}
