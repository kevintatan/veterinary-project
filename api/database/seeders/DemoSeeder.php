<?php

namespace Database\Seeders;

use App\Actions\Members\AddMember;
use App\Auth\ClinicRole;
use App\Models\Branch;
use App\Models\Clinic;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Database\Seeder;

/**
 * Local demo data (never runs in production). All logins use: password
 *  - admin@anipet.test       admin of Anipet
 *  - vet@anipet.test         veterinarian in Anipet AND admin in Huellitas (multi-clinic)
 *  - recepcion@anipet.test   receptionist of Anipet
 */
class DemoSeeder extends Seeder
{
    public function run(TenantContext $tenant, AddMember $addMember): void
    {
        // Demo data has known logins ("password"): never in production, even if run by hand.
        if (! app()->environment('local', 'testing')) {
            throw new \RuntimeException('Datos de demostración: solo en local.');
        }

        $users = collect([
            'admin' => ['Carolina Restrepo', 'admin@anipet.test'],
            'vet' => ['Andrés Mejía', 'vet@anipet.test'],
            'reception' => ['Paola Díaz', 'recepcion@anipet.test'],
        ])->map(fn (array $u) => User::query()->firstOrCreate(
            ['email' => $u[1]],
            ['name' => $u[0], 'password' => 'password', 'email_verified_at' => now()],
        ));

        $this->clinic($tenant, 'Veterinaria Anipet', 'anipet', ['Sede Cabecera', 'Sede Cañaveral'], function () use ($addMember, $users) {
            $addMember($users['admin'], ClinicRole::Admin);
            $addMember($users['vet'], ClinicRole::Veterinarian);
            $addMember($users['reception'], ClinicRole::Receptionist, [Branch::where('name', 'Sede Cabecera')->value('id')]);
        });

        $this->clinic($tenant, 'Clínica Huellitas', 'huellitas', ['Sede Principal'], function () use ($addMember, $users) {
            $addMember($users['vet'], ClinicRole::Admin);
        });

        $this->call([DemoPatientsSeeder::class, DemoAgendaSeeder::class, DemoHistorySeeder::class, DemoTodaySeeder::class, DemoRemindersSeeder::class, DemoPortalSeeder::class]);
    }

    /**
     * @param  list<string>  $branches
     */
    private function clinic(TenantContext $tenant, string $name, string $slug, array $branches, callable $members): void
    {
        if (Clinic::where('slug', $slug)->exists()) {
            return;
        }

        $clinic = Clinic::create(['name' => $name, 'slug' => $slug, 'status' => Clinic::STATUS_ACTIVE]);

        $tenant->runAs($clinic->id, function () use ($branches, $members) {
            Subscription::create([
                'plan_id' => Plan::where('code', Plan::PRO)->firstOrFail()->id,
                'status' => Subscription::STATUS_TRIALING,
                'trial_ends_at' => now()->addDays(Subscription::TRIAL_DAYS),
            ]);

            foreach ($branches as $branch) {
                Branch::create(['name' => $branch]);
            }

            $members();
        });
    }
}
