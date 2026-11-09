<?php

namespace Database\Seeders;

use App\Actions\Clinical\SaveClinicalRecord;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Catalogs\Vaccine;
use App\Models\Clinic;
use App\Models\Clinical\ClinicalRecord;
use App\Models\Pet;
use App\Models\Service;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Something to remind (Fase 4a demos): appointments for tomorrow and vaccines
 * or dewormings coming due this week, for Veterinaria Anipet.
 * Run any day: php artisan db:seed --class=DemoRemindersSeeder (idempotent).
 */
class DemoRemindersSeeder extends Seeder
{
    /** [time, pet, service, vet email] */
    private const TOMORROW = [
        ['08:30', 'Rocky', 'Consulta general', 'vet@anipet.test'],
        ['09:30', 'Canela', 'Vacunación', 'admin@anipet.test'],
        ['10:30', 'Tobías', 'Control / revisión', 'vet@anipet.test'],
        ['11:30', 'Michi', 'Baño y peluquería', 'admin@anipet.test'],
    ];

    /** [pet, type, vaccine or product, due in N days] */
    private const DUE = [
        ['Luna', 'vaccination', 'Rabia', 2],
        ['Kira', 'vaccination', 'Polivalente canina (DHPPi)', 4],
        ['Michi', 'deworming', 'Milbemax gatos', 6],
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
            $today = Carbon::now($clinic->timezone)->startOfDay();
            $tomorrow = $today->copy()->addDay();
            $branch = Branch::where('name', 'Sede Cabecera')->firstOrFail();

            $hasTomorrow = Appointment::whereBetween('starts_at', [$tomorrow->copy()->utc(), $tomorrow->copy()->endOfDay()->utc()])->exists();

            foreach ($hasTomorrow ? [] : self::TOMORROW as [$time, $petName, $serviceName, $vetEmail]) {
                $pet = Pet::where('name', $petName)->firstOrFail();
                $service = Service::where('name', $serviceName)->firstOrFail();
                $start = Carbon::parse($tomorrow->toDateString().' '.$time, $clinic->timezone);

                // Created directly (not through ScheduleAppointment) so it works whatever tomorrow's hours are.
                $appointment = Appointment::create([
                    'branch_id' => $branch->id,
                    'pet_id' => $pet->id,
                    'owner_id' => $pet->owner_id,
                    'service_id' => $service->id,
                    'starts_at' => $start,
                    'ends_at' => $start->copy()->addMinutes($service->duration_minutes),
                    'status' => 'scheduled',
                ]);
                $appointment->users()->attach(User::where('email', $vetEmail)->value('id'), ['clinic_id' => $clinic->id, 'role' => 'primary']);
            }

            $hasDue = ClinicalRecord::whereBetween('next_control_at', [$today->toDateString(), $today->copy()->addDays(7)->toDateString()])->exists();
            $vet = User::where('email', 'vet@anipet.test')->firstOrFail();

            foreach ($hasDue ? [] : self::DUE as [$petName, $type, $name, $inDays]) {
                $pet = Pet::where('name', $petName)->firstOrFail();
                $dueOn = $today->copy()->addDays($inDays);

                $save($clinic, $vet, $pet, [
                    'type' => $type,
                    'branch_id' => $branch->id,
                    'occurred_at' => $dueOn->copy()->subYear()->setTime(10, 0)->format('Y-m-d H:i'),
                    'next_control_at' => $dueOn->toDateString(),
                    ...($type === 'vaccination'
                        ? ['vaccination' => ['vaccine_id' => Vaccine::where('name', $name)->value('id'), 'vaccine_name' => $name, 'dose_number' => 1, 'route' => 'SC']]
                        : ['deworming' => ['kind' => 'internal', 'product_name' => $name, 'dose' => '1 tableta']]),
                ]);
            }
        });
    }
}
