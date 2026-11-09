<?php

namespace Database\Seeders;

use App\Actions\Clinical\SaveClinicalRecord;
use App\Models\Branch;
use App\Models\Catalogs\Vaccine;
use App\Models\Clinic;
use App\Models\Clinical\ClinicalRecord;
use App\Models\Owner;
use App\Models\Pet;
use App\Models\TutorAccount;
use App\Models\TutorAccountOwner;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Demo owner portal account (Fase 4e), local only: Paola Andrea Díaz, owner
 * of Rocky at Veterinaria Anipet. Sign in at /portal/ingresar with
 * paola@anipet.test / password1 (the portal requires letters + numbers).
 *
 * Rocky gets a full vaccination schedule (one of them expired), so the card
 * shows how it handles more vaccines than fit. Idempotent.
 */
class DemoPortalSeeder extends Seeder
{
    /** [vaccine (catalog name), applied N months ago, booster in N months from the application] */
    private const ROCKY_VACCINES = [
        ['Rabia', 2, 12],
        ['Polivalente canina con Leptospira (DHPPi+L)', 3, 12],
        ['Tos de las perreras (Bordetella / Parainfluenza)', 5, 12],
        ['Coronavirus canino', 14, 12], // expired
        ['Leptospirosis', 7, 12],
    ];

    public function run(TenantContext $tenant, SaveClinicalRecord $save): void
    {
        // Demo data has known logins ("password"): never in production, even if run by hand.
        if (! app()->environment('local', 'testing')) {
            throw new \RuntimeException('Datos de demostración: solo en local.');
        }

        $clinic = Clinic::where('slug', 'anipet')->first();
        if ($clinic === null) {
            return;
        }

        $tenant->runAs($clinic->id, function () use ($clinic, $save) {
            $owner = Owner::where('mobile_phone', '+573186665544')->first();
            if ($owner === null) {
                return;
            }

            $account = TutorAccount::firstOrCreate(
                ['phone' => $owner->mobile_phone],
                ['name' => $owner->full_name, 'email' => 'paola@anipet.test', 'password' => 'password1'],
            );

            TutorAccountOwner::firstOrCreate(
                ['owner_id' => $owner->id],
                ['tutor_account_id' => $account->id, 'link_method' => 'invitation', 'linked_at' => now()],
            );

            $rocky = Pet::where('owner_id', $owner->id)->where('name', 'Rocky')->first();
            if ($rocky === null || ClinicalRecord::where('pet_id', $rocky->id)->where('type', 'vaccination')->exists()) {
                return;
            }

            $vet = User::where('email', 'vet@anipet.test')->firstOrFail();
            $branch = Branch::where('name', 'Sede Cabecera')->firstOrFail();
            $today = Carbon::now($clinic->timezone)->startOfDay();

            foreach (self::ROCKY_VACCINES as [$name, $monthsAgo, $boosterMonths]) {
                $applied = $today->copy()->subMonthsNoOverflow($monthsAgo)->setTime(10, 0);

                $save($clinic, $vet, $rocky, [
                    'type' => 'vaccination',
                    'branch_id' => $branch->id,
                    'occurred_at' => $applied->format('Y-m-d H:i'),
                    'next_control_at' => $applied->copy()->addMonthsNoOverflow($boosterMonths)->toDateString(),
                    'vaccination' => ['vaccine_id' => Vaccine::where('name', $name)->value('id'), 'vaccine_name' => $name, 'dose_number' => 1, 'route' => 'SC'],
                ]);
            }
        });
    }
}
