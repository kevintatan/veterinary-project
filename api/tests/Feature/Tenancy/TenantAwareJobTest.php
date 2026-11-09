<?php

use App\Models\Branch;
use App\Models\Clinic;
use App\Tenancy\Jobs\RunInClinic;
use App\Tenancy\Jobs\TenantAware;
use App\Tenancy\TenancyViolation;

/** Minimal tenant-aware job used only in these tests. */
class CountBranchesJob
{
    use TenantAware;

    public ?int $seen = null;

    public function __construct()
    {
        $this->captureClinic();
    }

    public function handle(): void
    {
        $this->seen = Branch::count();
    }
}

function runJob(object $job): void
{
    (new RunInClinic)->handle($job, fn ($job) => $job->handle());
}

beforeEach(function () {
    $this->clinicA = Clinic::factory()->create();
    $this->clinicB = Clinic::factory()->create();

    tenant()->runAs($this->clinicA->id, fn () => Branch::create(['name' => 'A1']));
    tenant()->runAs($this->clinicB->id, function () {
        Branch::create(['name' => 'B1']);
        Branch::create(['name' => 'B2']);
    });
});

it('captures the clinic in context when the job is created', function () {
    inClinic($this->clinicA);

    expect((new CountBranchesJob)->clinicId)->toBe($this->clinicA->id);
});

it('cannot be created without a clinic in context', function () {
    tenant()->forget();

    new CountBranchesJob;
})->throws(TenancyViolation::class);

it('runs in its own clinic even if the worker had another one, and clears it after', function () {
    $jobForA = tenant()->runAs($this->clinicA->id, fn () => new CountBranchesJob);

    inClinic($this->clinicB); // leftover from a previous job in the same worker

    runJob($jobForA);

    expect($jobForA->seen)->toBe(1)
        ->and(tenant()->has())->toBeFalse();
});

it('clears the clinic even when the job fails', function () {
    $job = tenant()->runAs($this->clinicA->id, fn () => new CountBranchesJob);

    try {
        (new RunInClinic)->handle($job, fn () => throw new RuntimeException('boom'));
    } catch (RuntimeException) {
    }

    expect(tenant()->has())->toBeFalse();
});
