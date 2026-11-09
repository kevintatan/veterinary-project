<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Clinic;
use App\Models\Schedule;
use App\Models\Service;
use App\Tenancy\TenantContext;
use Illuminate\Database\Seeder;

/**
 * Demo services and opening hours for Veterinaria Anipet (local only). Idempotent.
 */
class DemoAgendaSeeder extends Seeder
{
    private const SERVICES = [
        ['Consulta general', 'consultation', 30, 60000, '#4F46E5'],
        ['Control / revisión', 'consultation', 20, 35000, '#6366F1'],
        ['Vacunación', 'vaccination', 15, 45000, '#10B981'],
        ['Desparasitación', 'deworming', 15, 25000, '#84CC16'],
        ['Baño y peluquería', 'grooming', 60, 55000, '#F59E0B'],
        ['Esterilización', 'surgery', 120, 350000, '#EF4444'],
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

        $tenant->runAs($clinic->id, function () {
            foreach (self::SERVICES as [$name, $category, $minutes, $price, $color]) {
                Service::query()->firstOrCreate(
                    ['name' => $name],
                    ['category' => $category, 'duration_minutes' => $minutes, 'price' => $price, 'color' => $color, 'is_bookable_online' => $category !== 'surgery'],
                );
            }

            // Mon–Fri 08:00–12:00 and 14:00–18:00, Sat 08:00–12:00, in every branch.
            foreach (Branch::all() as $branch) {
                if (Schedule::where('branch_id', $branch->id)->exists()) {
                    continue;
                }

                foreach (range(1, 6) as $weekday) {
                    Schedule::create(['branch_id' => $branch->id, 'weekday' => $weekday, 'start_time' => '08:00', 'end_time' => '12:00']);

                    if ($weekday <= 5) {
                        Schedule::create(['branch_id' => $branch->id, 'weekday' => $weekday, 'start_time' => '14:00', 'end_time' => '18:00']);
                    }
                }
            }
        });
    }
}
