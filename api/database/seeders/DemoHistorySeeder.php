<?php

namespace Database\Seeders;

use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Clinic;
use App\Models\Pet;
use App\Models\Service;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * 45 days of past appointments for Veterinaria Anipet, so the activity charts
 * have something to show in local demos. Deterministic (fixed random seed).
 * Run: php artisan db:seed --class=DemoHistorySeeder (skips if history exists).
 */
class DemoHistorySeeder extends Seeder
{
    private const DAYS = 45;

    /** [service name, weight] — how often each service is booked. */
    private const MIX = [
        ['Consulta general', 40],
        ['Vacunación', 22],
        ['Control / revisión', 15],
        ['Baño y peluquería', 12],
        ['Desparasitación', 8],
        ['Esterilización', 3],
    ];

    public function run(TenantContext $tenant): void
    {
        // Demo data has known logins ("password"): never in production, even if run by hand.
        if (! app()->environment('local', 'testing')) {
            throw new \RuntimeException('Datos de demostración: solo en local.');
        }

        $clinic = Clinic::where('slug', 'anipet')->first();

        if ($clinic === null) {
            return;
        }

        mt_srand(20261001);

        $tenant->runAs($clinic->id, function () use ($clinic) {
            $today = Carbon::now($clinic->timezone)->startOfDay();

            if (Appointment::where('starts_at', '<', $today->copy()->subDays(2)->utc())->exists()) {
                return;
            }

            $branch = Branch::where('name', 'Sede Cabecera')->firstOrFail();
            $vets = User::whereIn('email', ['vet@anipet.test', 'admin@anipet.test'])->get();
            $pets = Pet::all();
            $services = Service::whereIn('name', array_column(self::MIX, 0))->get()->keyBy('name');

            for ($i = self::DAYS; $i >= 1; $i--) {
                $day = $today->copy()->subDays($i);

                if ($day->isSunday()) {
                    continue;
                }

                $count = $day->isSaturday() ? mt_rand(2, 4) : mt_rand(3, 8);
                $slots = collect(range(8 * 2, 17 * 2))->reject(fn ($s) => $s >= 24 && $s < 28)->shuffle()->take($count);

                foreach ($slots as $slot) {
                    $service = $services[$this->pickService()];
                    $start = $day->copy()->addMinutes($slot * 30);
                    $roll = mt_rand(1, 100);
                    $status = $roll <= 9 ? 'no_show' : ($roll <= 16 ? 'cancelled' : 'completed');

                    $appointment = Appointment::create([
                        'branch_id' => $branch->id,
                        'pet_id' => ($pet = $pets->random())->id,
                        'owner_id' => $pet->owner_id,
                        'service_id' => $service->id,
                        'starts_at' => $start,
                        'ends_at' => $start->copy()->addMinutes($service->duration_minutes),
                        'status' => $status,
                        'cancellation_reason' => $status === 'cancelled' ? 'Cancelada por el tutor' : null,
                    ]);
                    $appointment->users()->attach($vets->random()->id, ['clinic_id' => $branch->clinic_id, 'role' => 'primary']);
                }
            }

            // Spread pet registrations over the period (new patients metric).
            $pets->each(function (Pet $pet, int $index) use ($today) {
                $pet->forceFill(['created_at' => $today->copy()->subDays(5 + $index * 9)])->saveQuietly();
            });
        });
    }

    private function pickService(): string
    {
        $roll = mt_rand(1, array_sum(array_column(self::MIX, 1)));

        foreach (self::MIX as [$name, $weight]) {
            if (($roll -= $weight) <= 0) {
                return $name;
            }
        }

        return self::MIX[0][0];
    }
}
