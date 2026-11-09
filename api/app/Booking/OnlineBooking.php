<?php

namespace App\Booking;

use App\Actions\Agenda\AvailableSlots;
use App\Actions\Agenda\ScheduleAppointment;
use App\Auth\Permission;
use App\Enums\AppointmentStatus;
use App\Jobs\SendBookingMessage;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Clinic;
use App\Models\Service;
use App\Models\Subscription;
use App\Notifications\Staff\NotifyStaff;
use App\Support\ClinicTime;
use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Output\QRGdImagePNG;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Online booking (docs/reservas.md). Always called inside the clinic's
 * tenant context. A booking is a *request*: it holds the slot and waits for
 * the clinic to accept it (AcceptBookingRequest) or reject it.
 */
final class OnlineBooking
{
    /** Pending requests one phone can have at the same time in a clinic (anti-abuse). */
    public const MAX_PENDING_PER_PHONE = 2;

    /** Documents a person can book with (no NIT, TI or civil registry). */
    public const DOCUMENT_TYPES = ['CC', 'CE', 'PPT', 'PAS', 'DIE'];

    public function __construct(
        private readonly AvailableSlots $availableSlots,
        private readonly ScheduleAppointment $schedule,
        private readonly NotifyStaff $notify,
    ) {}

    /**
     * @return array{enabled: bool, min_notice_hours: int, max_days_ahead: int, choose_professional: bool, message: string|null}
     */
    public static function config(Clinic $clinic): array
    {
        return array_merge(Clinic::DEFAULT_SETTINGS['online_booking'], $clinic->settings['online_booking'] ?? []);
    }

    /** The public page, e.g. https://zooetia.co/reservar/anipet */
    public static function publicUrl(Clinic $clinic): string
    {
        return rtrim((string) config('app.frontend_url'), '/').'/reservar/'.$clinic->slug;
    }

    /** QR of the public page (PNG bytes), for the counter poster and social networks. */
    public static function qrPng(Clinic $clinic, int $scale = 12): string
    {
        return (new QRCode(new QROptions([
            'outputInterface' => QRGdImagePNG::class,
            'outputBase64' => false,
            'eccLevel' => EccLevel::M,
            'scale' => $scale,
            'addQuietzone' => true,
        ])))->render(self::publicUrl($clinic));
    }

    /** Turned on by the clinic and included in its plan. */
    public static function isOpen(Clinic $clinic): bool
    {
        return self::config($clinic)['enabled'] && Subscription::allows('online_booking');
    }

    /**
     * Times that can be requested on a date: union of the professionals' free
     * slots, within the clinic's notice and horizon.
     *
     * @return list<array{starts_at: string, user_ids: list<string>}>
     */
    public function slots(Clinic $clinic, Branch $branch, Service $service, string $date, ?string $userId = null): array
    {
        $config = self::config($clinic);
        $earliest = now()->addHours($config['min_notice_hours']);
        $latest = now()->setTimezone($clinic->timezone)->startOfDay()->addDays($config['max_days_ahead'] + 1);

        $byStart = [];

        foreach (($this->availableSlots)($clinic, $branch->id, $date, $service->duration_minutes, $userId) as $row) {
            foreach ($row['slots'] as $slot) {
                /** @var Carbon $start */
                $start = $slot['starts_at'];

                if ($start->lt($earliest) || $start->gte($latest)) {
                    continue;
                }

                $key = $start->toIso8601String();
                $byStart[$key] ??= ['starts_at' => ClinicTime::iso($start, $clinic), 'user_ids' => [], 'sort' => $start->timestamp];
                $byStart[$key]['user_ids'][] = $row['user']['id'];
            }
        }

        usort($byStart, fn ($a, $b) => $a['sort'] <=> $b['sort']);

        return array_map(fn ($s) => ['starts_at' => $s['starts_at'], 'user_ids' => $s['user_ids']], $byStart);
    }

    /**
     * "Cualquiera disponible": whoever has the fewest appointments that day,
     * so online requests don't all land on the same professional.
     *
     * @param  list<string>  $userIds  free at that time
     */
    private function leastBusy(array $userIds, Carbon $start, Clinic $clinic): string
    {
        if (count($userIds) === 1) {
            return $userIds[0];
        }

        $dayStart = $start->copy()->setTimezone($clinic->timezone)->startOfDay()->utc();
        $dayEnd = $dayStart->copy()->addDay();

        return collect($userIds)
            ->sortBy(fn (string $id) => Appointment::query()->blockingTime()->overlapping($dayStart, $dayEnd)->whereHas('users', fn ($q) => $q->where('users.id', $id))->count())
            ->first();
    }

    /**
     * @param  array{branch_id: string, service_id: string, starts_at: string, user_id?: string|null,
     *               name: string, phone: string, document_type: string, document_number: string, pet_name: string, species_id: string, species_name: string, notes?: string|null}  $data
     */
    public function request(Clinic $clinic, array $data): Appointment
    {
        $branch = Branch::where('is_active', true)->findOrFail($data['branch_id']);
        $service = Service::where('is_active', true)->where('is_bookable_online', true)->findOrFail($data['service_id']);
        $start = ClinicTime::parse($data['starts_at'], $clinic);

        $pending = Appointment::query()
            ->where('status', AppointmentStatus::Requested)
            ->where(fn ($q) => $q->where('booking_details->phone', $data['phone'])->orWhere('booking_details->document_number', $data['document_number']))
            ->where('starts_at', '>', now())
            ->count();

        if ($pending >= self::MAX_PENDING_PER_PHONE) {
            throw ValidationException::withMessages(['phone' => 'Ya tienes solicitudes pendientes en esta clínica. Espera su respuesta antes de pedir otra cita.']);
        }

        // The time must still be free (and allowed) right now; pick the professional.
        $date = $start->copy()->setTimezone($clinic->timezone)->toDateString();
        $slot = collect($this->slots($clinic, $branch, $service, $date, $data['user_id'] ?? null))
            ->first(fn ($s) => ClinicTime::parse($s['starts_at'], $clinic)->equalTo($start));

        if ($slot === null) {
            throw ValidationException::withMessages(['starts_at' => 'Esa hora ya no está disponible. Elige otra, por favor.']);
        }

        $appointment = ($this->schedule)($clinic, [
            'branch_id' => $branch->id,
            'service_id' => $service->id,
            'starts_at' => $start,
            'user_ids' => [$this->leastBusy($slot['user_ids'], $start, $clinic)],
            'status' => AppointmentStatus::Requested->value,
            'source' => 'online_booking',
            'booking_details' => [
                'name' => $data['name'],
                'phone' => $data['phone'],
                'document_type' => $data['document_type'],
                'document_number' => $data['document_number'],
                'pet_name' => $data['pet_name'],
                'species_id' => $data['species_id'],
                'species_name' => $data['species_name'],
                'notes' => $data['notes'] ?? null,
                'professional_chosen' => ! empty($data['user_id']),
                // Ley 1581: what the person authorized when sending the request.
                'consents' => ['data_processing' => true, 'whatsapp' => true],
                'requested_at' => now()->toIso8601String(),
            ],
        ], null);

        SendBookingMessage::dispatch($appointment->id, SendBookingMessage::RECEIVED)->afterCommit();

        $local = $start->copy()->setTimezone($clinic->timezone);
        $this->notify->withPermission(
            Permission::AppointmentsManage,
            'booking_request',
            'Nueva solicitud de cita en línea',
            "{$data['name']} pide {$service->name} para {$data['pet_name']} el ".$local->translatedFormat('D j \d\e M').', '.$local->format('g:i').($local->hour < 12 ? ' a. m.' : ' p. m.'), // "a. m." already ends the sentence
            '/app/agenda/solicitudes',
        );

        return $appointment;
    }
}
