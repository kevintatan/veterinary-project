<?php

namespace App\Tenancy\Jobs;

use App\Tenancy\TenancyViolation;
use App\Tenancy\TenantContext;
use Closure;
use Illuminate\Queue\Jobs\SyncJob;

/**
 * Job middleware: runs the job in its clinic.
 *
 * - In a worker it ALWAYS clears the clinic afterwards, so a long-running
 *   worker never carries a clinic (not even a leftover one) into the next job.
 * - Run synchronously inside a request (sync queue: tests, or a dispatch
 *   during a request), it gives the request its own clinic back.
 */
final class RunInClinic
{
    public function handle(object $job, Closure $next): mixed
    {
        $clinicId = $job->clinicId ?? throw TenancyViolation::missingContext();
        $context = app(TenantContext::class);

        if (($job->job ?? null) instanceof SyncJob) {
            return $context->runAs($clinicId, fn () => $next($job));
        }

        $context->set($clinicId);

        try {
            return $next($job);
        } finally {
            $context->forget();
        }
    }
}
