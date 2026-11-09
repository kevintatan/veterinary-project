<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\AppointmentStatus;
use App\Enums\ConsentType;
use App\Http\Controllers\Controller;
use App\Messaging\ReminderTexts;
use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\Clinical\ClinicalRecord;
use App\Models\Message;
use App\Models\Owner;
use App\Models\Reminder;
use App\Support\ClinicTime;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Manual reminders (Fase 4a): what to remind (appointments of a day, upcoming
 * vaccines/controls) and a wa.me link with the message already written, sent
 * from the clinic's own WhatsApp. Every link opened is logged in `messages`.
 */
class ReminderController extends Controller
{
    private const PURPOSES = ['appointment' => 'appointment_reminder', 'control' => 'next_control'];

    /** @var array<string, array<string, bool>> owner id => consents (memo per request) */
    private array $consents = [];

    public function due(Request $request): JsonResponse
    {
        $data = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
            'days' => ['nullable', 'integer', 'between:1,60'],
        ], [], trans('fields'));

        $clinic = $this->clinic($request);
        $today = Carbon::now($clinic->timezone)->startOfDay();
        $date = isset($data['date']) ? Carbon::parse($data['date'], $clinic->timezone) : $today->copy()->addDay();
        $days = (int) ($data['days'] ?? 7);

        $appointments = Appointment::query()
            ->with(['owner', 'pet', 'service'])
            ->whereIn('status', [AppointmentStatus::Scheduled, AppointmentStatus::Confirmed])
            ->whereBetween('starts_at', [$date->copy()->startOfDay()->utc(), $date->copy()->endOfDay()->utc()])
            ->whereNotNull('pet_id')
            ->orderBy('starts_at')
            ->get();

        $controls = ClinicalRecord::query()
            ->with(['pet.owner', 'vaccination', 'deworming'])
            ->notVoided()
            ->whereBetween('next_control_at', [$today->toDateString(), $today->copy()->addDays($days)->toDateString()])
            ->whereHas('pet', fn (Builder $q) => $q->whereNull('deceased_at'))
            ->unanswered()
            ->orderBy('next_control_at')
            ->get();

        $upcoming = Appointment::query()
            ->whereIn('pet_id', $controls->pluck('pet_id'))
            ->whereIn('status', [AppointmentStatus::Scheduled, AppointmentStatus::Confirmed]) // same rule as SendReminder
            ->where('starts_at', '>', now())
            ->pluck('pet_id')
            ->flip();

        $last = $this->lastReminders([...$appointments, ...$controls], $clinic);
        $auto = $this->automatic([...$appointments, ...$controls], $clinic);

        return response()->json(['data' => [
            'date' => $date->toDateString(),
            'appointments' => $appointments->map(fn (Appointment $a) => [
                'id' => $a->id,
                'starts_at' => ClinicTime::iso($a->starts_at, $clinic),
                'is_time_unset' => $a->is_time_unset,
                'status' => ['value' => $a->status->value, 'label' => $a->status->label()],
                'service' => $a->service?->name,
                'pet' => ['id' => $a->pet->id, 'name' => $a->pet->name],
                ...$this->contact($a->owner, $a->pet->deceased_at !== null),
                'last_reminder' => $last[$a->id] ?? null,
                'automatic' => $auto[$a->id] ?? null,
            ])->values(),
            'controls' => $controls->map(fn (ClinicalRecord $r) => [
                'id' => $r->id,
                'type' => ['value' => $r->type, 'label' => $r->typeLabel()],
                'summary' => match ($r->type) {
                    'vaccination' => $r->vaccination?->vaccine_name,
                    'deworming' => $r->deworming?->product_name,
                    default => $r->next_control_note,
                },
                'due_on' => $r->next_control_at->toDateString(),
                'pet' => ['id' => $r->pet->id, 'name' => $r->pet->name],
                'has_upcoming_appointment' => $upcoming->has($r->pet_id),
                ...$this->contact($r->pet->owner, false),
                'last_reminder' => $last[$r->id] ?? null,
                'automatic' => $auto[$r->id] ?? null,
            ])->values(),
        ]]);
    }

    /**
     * Builds the message and the wa.me link, after checking the owner's
     * WhatsApp authorization (Ley 1581), and logs it.
     */
    public function manual(Request $request, ReminderTexts $texts): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys(self::PURPOSES))],
            'id' => ['required', 'uuid'],
        ], [], trans('fields'));

        $clinic = $this->clinic($request);

        /** @var Appointment|ClinicalRecord $subject */
        $subject = $data['type'] === 'appointment'
            ? Appointment::with(['owner', 'pet'])->findOrFail($data['id'])
            : ClinicalRecord::notVoided()->whereNotNull('next_control_at')->with('pet.owner')->findOrFail($data['id']);

        $owner = $subject instanceof Appointment ? $subject->owner : $subject->pet?->owner;
        $contact = $this->contact($owner, $subject->pet?->deceased_at !== null);

        if (! $contact['whatsapp_allowed']) {
            throw ValidationException::withMessages(['id' => $contact['whatsapp_blocked_reason']]);
        }

        $body = ($subject instanceof Appointment ? $texts->appointment($subject, $clinic) : $texts->nextControl($subject, $clinic))->body;

        Message::create([
            'owner_id' => $owner->id,
            'channel' => 'whatsapp',
            'direction' => 'outbound',
            'provider' => Message::PROVIDER_MANUAL,
            'purpose' => self::PURPOSES[$data['type']],
            'to_phone' => $owner->mobile_phone,
            'body' => $body,
            'status' => Message::STATUS_OPENED,
            'related_type' => $subject->getMorphClass(),
            'related_id' => $subject->id,
            'sent_by' => $request->user()->id,
            'sent_at' => now(),
        ]);

        return response()->json(['data' => [
            'body' => $body,
            'url' => 'https://wa.me/'.ltrim((string) $owner->mobile_phone, '+').'?text='.rawurlencode($body),
        ]]);
    }

    /**
     * @return array{owner: array<string, mixed>|null, whatsapp_allowed: bool, whatsapp_blocked_reason: ?string}
     */
    private function contact(?Owner $owner, bool $deceased): array
    {
        $reason = match (true) {
            $owner === null => 'La cita no tiene tutor asignado.',
            $deceased => 'La mascota falleció.',
            blank($owner->mobile_phone) => 'El tutor no tiene celular registrado.',
            ! $this->consentsOf($owner)[ConsentType::ChannelWhatsapp->value] => 'El tutor no autorizó mensajes por WhatsApp.',
            default => null,
        };

        return [
            'owner' => $owner === null ? null : ['id' => $owner->id, 'full_name' => $owner->full_name, 'mobile_phone' => $owner->mobile_phone],
            'whatsapp_allowed' => $reason === null,
            'whatsapp_blocked_reason' => $reason,
        ];
    }

    /** @return array<string, bool> */
    private function consentsOf(Owner $owner): array
    {
        return $this->consents[$owner->id] ??= $owner->currentConsents();
    }

    /**
     * Latest reminder per subject (any provider), for "recordado hace 2 h por Carolina".
     *
     * @param  list<Model>  $subjects
     * @return Collection<string, array<string, mixed>>
     */
    private function lastReminders(array $subjects, Clinic $clinic): Collection
    {
        if ($subjects === []) {
            return collect();
        }

        return Message::query()
            ->with('sender:id,name')
            ->where('direction', 'outbound')
            ->whereIn('related_id', collect($subjects)->pluck('id'))
            ->latest('sent_at')
            ->get()
            ->unique('related_id')
            ->mapWithKeys(fn (Message $m) => [$m->related_id => [
                'sent_at' => ClinicTime::iso($m->sent_at, $clinic),
                'provider' => $m->provider,
                'by' => $m->sender?->name,
            ]]);
    }

    /**
     * The automatic reminder of each subject (Fase 4c), so staff know it will go out on its own.
     *
     * @param  list<Model>  $subjects
     * @return Collection<string, array<string, mixed>>
     */
    private function automatic(array $subjects, Clinic $clinic): Collection
    {
        if ($subjects === []) {
            return collect();
        }

        return Reminder::query()
            ->whereIn('remindable_id', collect($subjects)->pluck('id'))
            ->where('status', '!=', Reminder::CANCELLED)
            ->latest('updated_at')
            ->get()
            ->unique('remindable_id')
            ->mapWithKeys(fn (Reminder $r) => [$r->remindable_id => [
                'status' => ['value' => $r->status, 'label' => $r->statusLabel()],
                'scheduled_for' => ClinicTime::iso($r->scheduled_for, $clinic),
            ]]);
    }

    private function clinic(Request $request): Clinic
    {
        return $request->attributes->get('clinic');
    }
}
