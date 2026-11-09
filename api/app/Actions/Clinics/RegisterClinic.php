<?php

namespace App\Actions\Clinics;

use App\Actions\Members\AddMember;
use App\Auth\ClinicRole;
use App\Models\Branch;
use App\Models\Clinic;
use App\Models\Plan;
use App\Models\ReminderRule;
use App\Models\Subscription;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Sign-up: new clinic + main branch + admin user + 15-day Pro trial.
 */
final class RegisterClinic
{
    public function __construct(
        private readonly TenantContext $tenant,
        private readonly AddMember $addMember,
    ) {}

    /**
     * @param  array{clinic_name: string, name: string, email: string, password: string, phone?: string|null}  $data
     * @return array{user: User, clinic: Clinic}
     */
    public function __invoke(array $data): array
    {
        return DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => $data['name'],
                'email' => Str::lower($data['email']),
                'password' => $data['password'],
                'phone' => $data['phone'] ?? null,
            ]);
            $user->forceFill(['terms_accepted_at' => now(), 'terms_version' => config('app.terms_version')])->save();

            $clinic = Clinic::create([
                'name' => $data['clinic_name'],
                'slug' => $this->uniqueSlug($data['clinic_name']),
                'email' => Str::lower($data['email']),
                'phone' => $data['phone'] ?? null,
                'status' => Clinic::STATUS_ACTIVE,
            ]);

            $this->tenant->runAs($clinic->id, function () use ($user) {
                Subscription::create([
                    'plan_id' => Plan::where('code', Plan::PRO)->firstOrFail()->id,
                    'status' => Subscription::STATUS_TRIALING,
                    'trial_ends_at' => now()->addDays(Subscription::TRIAL_DAYS),
                ]);

                Branch::create(['name' => 'Sede principal']);
                ReminderRule::createDefaults(); // 24 h before appointments, 7 days before controls

                ($this->addMember)($user, ClinicRole::Admin);
            });

            return ['user' => $user, 'clinic' => $clinic];
        });
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::limit(Str::slug($name), 70, '') ?: 'clinica';
        $slug = $base;

        for ($i = 2; Clinic::where('slug', $slug)->exists(); $i++) {
            $slug = "{$base}-{$i}";
        }

        return $slug;
    }
}
