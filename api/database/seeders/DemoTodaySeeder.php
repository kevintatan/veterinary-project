<?php

namespace Database\Seeders;

use App\Actions\Agenda\ChangeAppointmentStatus;
use App\Actions\Agenda\ScheduleAppointment;
use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Clinic;
use App\Models\Pet;
use App\Models\Service;
use App\Models\Task;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * A realistic "today" for Veterinaria Anipet (local demos/screenshots).
 * Run any day: php artisan db:seed --class=DemoTodaySeeder. Skips if today already has appointments.
 */
class DemoTodaySeeder extends Seeder
{
    /** [time, pet, service, vet email, final status] */
    private const DAY = [
        ['08:00', 'Kira', 'Control / revisión', 'vet@anipet.test', AppointmentStatus::Completed],
        ['09:00', 'Luna', 'Consulta general', 'vet@anipet.test', AppointmentStatus::CheckedIn],
        ['09:30', 'Tobías', 'Vacunación', 'admin@anipet.test', AppointmentStatus::Confirmed],
        ['10:30', 'Michi', 'Consulta general', 'vet@anipet.test', AppointmentStatus::Scheduled],
        ['11:00', 'Rocky', 'Baño y peluquería', 'admin@anipet.test', AppointmentStatus::Scheduled],
        ['15:00', 'Canela', 'Desparasitación', 'vet@anipet.test', AppointmentStatus::Cancelled],
    ];

    public function run(TenantContext $tenant, ScheduleAppointment $schedule, ChangeAppointmentStatus $changeStatus): void
    {
        // Demo data has known logins ("password"): never in production, even if run by hand.
        if (! app()->environment('local', 'testing')) {
            throw new \RuntimeException('Datos de demostración: solo en local.');
        }

        $clinic = Clinic::where('slug', 'anipet')->first();

        if ($clinic === null) {
            return;
        }

        $tenant->runAs($clinic->id, function () use ($clinic, $schedule, $changeStatus) {
            $today = Carbon::now($clinic->timezone)->startOfDay();

            if (Appointment::where('starts_at', '>=', $today->copy()->utc())->where('starts_at', '<', $today->copy()->addDay()->utc())->exists()) {
                return;
            }

            $branch = Branch::where('name', 'Sede Cabecera')->firstOrFail();

            foreach (self::DAY as [$time, $petName, $serviceName, $vetEmail, $status]) {
                $vet = User::where('email', $vetEmail)->firstOrFail();
                $appointment = $schedule($clinic, [
                    'branch_id' => $branch->id,
                    'pet_id' => Pet::where('name', $petName)->firstOrFail()->id,
                    'service_id' => Service::where('name', $serviceName)->firstOrFail()->id,
                    'starts_at' => Carbon::parse($today->toDateString().' '.$time, $clinic->timezone)->utc(),
                    'user_ids' => [$vet->id],
                ], $vet);

                foreach ($this->path($status) as $step) {
                    $changeStatus($appointment, $step, 'staff', $vet->id, null, $step === AppointmentStatus::Cancelled ? 'El tutor reprogramará' : null);
                }
            }

            $vet = User::where('email', 'vet@anipet.test')->firstOrFail();
            Task::create([
                'title' => 'Llamar a Carolina para saber cómo sigue Luna',
                'pet_id' => Pet::where('name', 'Luna')->value('id'),
                'owner_id' => Pet::where('name', 'Luna')->value('owner_id'),
                'assignee_id' => $vet->id,
                'created_by' => $vet->id,
                'due_at' => $today->copy()->setTime(17, 0)->utc(),
            ]);
            Task::create([
                'title' => 'Enviar resultados de laboratorio de Kira',
                'assignee_id' => $vet->id,
                'created_by' => $vet->id,
                'due_at' => $today->copy()->subDay()->setTime(12, 0)->utc(), // overdue
            ]);
        });
    }

    /**
     * @return list<AppointmentStatus>
     */
    private function path(AppointmentStatus $target): array
    {
        return match ($target) {
            AppointmentStatus::Scheduled => [],
            AppointmentStatus::Confirmed => [AppointmentStatus::Confirmed],
            AppointmentStatus::CheckedIn => [AppointmentStatus::Confirmed, AppointmentStatus::CheckedIn],
            AppointmentStatus::Completed => [AppointmentStatus::Confirmed, AppointmentStatus::CheckedIn, AppointmentStatus::InProgress, AppointmentStatus::Completed],
            AppointmentStatus::Cancelled => [AppointmentStatus::Cancelled],
            default => [$target],
        };
    }
}
