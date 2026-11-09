<?php

namespace App\Messaging\Bot;

use App\Actions\Agenda\AvailableSlots;
use App\Actions\Agenda\ChangeAppointmentStatus;
use App\Actions\Agenda\ScheduleAppointment;
use App\Auth\Permission;
use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\Message;
use App\Models\Owner;
use App\Models\Subscription;
use App\Models\WhatsAppConversation;
use App\Notifications\Staff\NotifyStaff;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * The confirmation bot (Fase 4d), no AI: buttons drive it.
 *
 *  Confirmar   → appointment confirmed (channel "whatsapp") + thanks.
 *  Cancelar    → "¿Seguro?" (Sí, cancelar / No) → cancelled with reason.
 *  Reprogramar → 3 real free slots as buttons → the appointment moves.
 *  Online request (status "requested", docs/reservas.md): only
 *                Cancelar solicitud → "¿Seguro?" → cancelled; anything else →
 *                "aún está en revisión".
 *  Free text   → plan with inbox: the team's inbox + one "te responderán pronto";
 *                otherwise one reply pointing to the clinic's own WhatsApp
 *                (the chat with the team is a higher-plan feature, docs/planes.md).
 *
 * Runs inside the clinic's tenant context (WebhookProcessor sets it).
 */
class WhatsAppBot
{
    /** Don't repeat the "we'll answer soon" auto-reply more than once in this time. */
    private const ACK_EVERY_MINUTES = 120;

    public function __construct(
        private readonly WhatsAppReplier $replier,
        private readonly ChangeAppointmentStatus $changeStatus,
        private readonly ScheduleAppointment $schedule,
        private readonly AvailableSlots $slots,
        private readonly NotifyStaff $notify,
    ) {}

    public function handle(Clinic $clinic, WhatsAppConversation $conversation, InboundMessage $in, ?Owner $owner): void
    {
        $parsed = BotActions::parse($in->payload);

        if ($parsed !== null) {
            [$action, $messageId, $extra] = $parsed;
            $appointment = Message::find($messageId)?->related;

            if ($appointment instanceof Appointment) {
                $this->act($clinic, $conversation, $in, $owner, $appointment, $action, $messageId, $extra);

                return;
            }
        }

        $this->handOff($clinic, $conversation, $in, $owner);
    }

    private function act(Clinic $clinic, WhatsAppConversation $conversation, InboundMessage $in, ?Owner $owner, Appointment $appointment, string $action, string $messageId, ?string $extra): void
    {
        $say = fn (string $body, array $buttons = []) => $this->replier->reply($clinic->id, $in->from, $owner?->id, $body, $buttons, $appointment);
        $pet = $appointment->pet?->name ?? ($appointment->booking_details['pet_name'] ?? 'tu mascota');

        if ($appointment->status === AppointmentStatus::Requested && ! $appointment->starts_at->isPast()) {
            $this->actOnRequest($clinic, $appointment, $action, $messageId, $pet, $say);

            return;
        }

        if (! in_array($appointment->status, [AppointmentStatus::Scheduled, AppointmentStatus::Confirmed], true) || $appointment->starts_at->isPast()) {
            $say("Esa cita de {$pet} ya no está vigente. Si necesitas algo, escríbenos por aquí y {$clinic->name} te ayuda.");

            return;
        }

        switch ($action) {
            case BotActions::CONFIRM:
                if ($appointment->status === AppointmentStatus::Scheduled) {
                    ($this->changeStatus)($appointment, AppointmentStatus::Confirmed, 'whatsapp', null, 'Confirmada por el tutor por WhatsApp');
                }
                $say($this->sentence("¡Gracias! Confirmamos la cita de {$pet} {$this->when($appointment->starts_at, $clinic)}")." Te esperamos en {$clinic->name}.");
                break;

            case BotActions::CANCEL:
                $say("¿Seguro que quieres cancelar la cita de {$pet} {$this->when($appointment->starts_at, $clinic)}?", [
                    [BotActions::CANCEL_YES.':'.$messageId, 'Sí, cancelar'],
                    [BotActions::CANCEL_NO.':'.$messageId, 'No, la mantengo'],
                ]);
                break;

            case BotActions::CANCEL_YES:
                ($this->changeStatus)($appointment, AppointmentStatus::Cancelled, 'whatsapp', null, null, 'Cancelada por el tutor por WhatsApp');
                $say("Listo, cancelamos la cita de {$pet}. Cuando quieras agendar otra, escríbenos por aquí.");
                $this->tellTeam($clinic, $appointment, "{$pet}: el tutor canceló la cita por WhatsApp");
                break;

            case BotActions::CANCEL_NO:
                $say($this->sentence("Perfecto, la cita de {$pet} sigue en pie {$this->when($appointment->starts_at, $clinic)}"));
                break;

            case BotActions::RESCHEDULE:
                $this->offerSlots($clinic, $appointment, $messageId, $say);
                break;

            case BotActions::SLOT:
                $this->moveTo($clinic, $conversation, $in, $owner, $appointment, $messageId, (string) $extra, $say);
                break;

            default:
                $this->handOff($clinic, $conversation, $in, $owner);
        }
    }

    /** Up to 3 free slots, on different days, with the same professional and duration. */
    private function offerSlots(Clinic $clinic, Appointment $appointment, string $messageId, callable $say): void
    {
        $options = $this->findSlots($clinic, $appointment);

        if ($options === []) {
            if (Subscription::allows('whatsapp_inbox')) {
                $say("No encontramos espacios libres en los próximos días. {$clinic->name} te escribirá para buscar otra fecha.");
                $this->flag($appointment->clinic_id, $appointment->owner?->mobile_phone);
            } else {
                $say("No encontramos espacios libres en los próximos días. {$this->contactClinic($clinic)}");
            }

            return;
        }

        $say('Estos son los próximos espacios libres. Elige uno o escríbenos la fecha que prefieres:', array_map(
            fn (Carbon $start) => [BotActions::SLOT.":{$messageId}:".$start->copy()->setTimezone($clinic->timezone)->format('YmdHi'), $this->shortSlot($start, $clinic)],
            $options,
        ));
    }

    private function moveTo(Clinic $clinic, WhatsAppConversation $conversation, InboundMessage $in, ?Owner $owner, Appointment $appointment, string $messageId, string $slot, callable $say): void
    {
        $start = Carbon::createFromFormat('YmdHi', $slot, $clinic->timezone);

        if ($start === null || ! $this->isFree($clinic, $appointment, $start)) {
            $say('Ese espacio ya no está disponible. Te mostramos otros:');
            $this->offerSlots($clinic, $appointment, $messageId, $say);

            return;
        }

        try {
            ($this->schedule)($clinic, [
                'branch_id' => $appointment->branch_id,
                'pet_id' => $appointment->pet_id,
                'service_id' => $appointment->service_id,
                'starts_at' => $start->copy()->utc(),
                'ends_at' => $start->copy()->utc()->addMinutes($appointment->starts_at->diffInMinutes($appointment->ends_at)),
                'user_ids' => $appointment->users()->pluck('users.id')->all(),
            ], null, $appointment);
        } catch (ValidationException) {
            $say('Ese espacio se acaba de ocupar. Te mostramos otros:');
            $this->offerSlots($clinic, $appointment, $messageId, $say);

            return;
        }

        $say($this->sentence("Listo, movimos la cita de {$appointment->pet?->name} {$this->when($appointment->fresh()->starts_at, $clinic)}").' ¡Te esperamos!');
        $this->tellTeam($clinic, $appointment->fresh(), "{$appointment->pet?->name}: el tutor reprogramó la cita por WhatsApp");
    }

    /** A request the clinic hasn't answered yet: the tutor can only withdraw it. */
    private function actOnRequest(Clinic $clinic, Appointment $request, string $action, string $messageId, string $pet, callable $say): void
    {
        switch ($action) {
            case BotActions::CANCEL:
                $say("¿Seguro que quieres cancelar tu solicitud de cita para {$pet} {$this->when($request->starts_at, $clinic)}?", [
                    [BotActions::CANCEL_YES.':'.$messageId, 'Sí, cancelar'],
                    [BotActions::CANCEL_NO.':'.$messageId, 'No, la mantengo'],
                ]);
                break;

            case BotActions::CANCEL_YES:
                ($this->changeStatus)($request, AppointmentStatus::Cancelled, 'whatsapp', null, 'Solicitud cancelada por el tutor', 'Cancelada por el tutor por WhatsApp');
                $say("Listo, cancelamos tu solicitud para {$pet}. Si quieres, puedes pedir otra hora cuando quieras.");
                $this->tellTeam($clinic, $request, "{$pet}: el tutor retiró su solicitud en línea");
                break;

            case BotActions::CANCEL_NO:
                $say("Perfecto, tu solicitud para {$pet} sigue en revisión. {$clinic->name} te responderá por aquí.");
                break;

            default:
                $say("Tu solicitud de cita para {$pet} aún está en revisión. {$clinic->name} te responderá por aquí.");
        }
    }

    /** The bell of whoever manages appointments: something changed without them. */
    private function tellTeam(Clinic $clinic, Appointment $appointment, string $title): void
    {
        $local = $appointment->starts_at->copy()->setTimezone($clinic->timezone);
        $this->notify->withPermission(
            Permission::AppointmentsManage,
            'whatsapp_reply',
            $title,
            Str::ucfirst($local->translatedFormat('l j \d\e F')).', '.$local->format('g:i').($local->hour < 12 ? ' a. m.' : ' p. m.'),
            '/app/agenda?fecha='.$local->toDateString(),
        );
    }

    /** @return list<Carbon> UTC starts */
    private function findSlots(Clinic $clinic, Appointment $appointment, int $max = 3): array
    {
        $minutes = max(15, $appointment->starts_at->diffInMinutes($appointment->ends_at));
        $vetId = $appointment->users()->wherePivot('role', 'primary')->value('users.id') ?? $appointment->users()->value('users.id');
        $found = [];
        $day = Carbon::now($clinic->timezone)->startOfDay();

        for ($i = 0; $i < 21 && count($found) < $max; $i++, $day->addDay()) {
            foreach (($this->slots)($clinic, $appointment->branch_id, $day->toDateString(), $minutes, $vetId) as $row) {
                $first = collect($row['slots'])->first(fn ($s) => $s['starts_at']->gt(now()->addHours(2)) && ! $s['starts_at']->equalTo($appointment->starts_at));
                if ($first !== null) {
                    $found[] = Carbon::instance($first['starts_at']);
                    break; // one per day: real choices, not three slots of the same morning
                }
            }
        }

        return $found;
    }

    /** Still free right now (someone may have booked it since we offered it). */
    private function isFree(Clinic $clinic, Appointment $appointment, Carbon $start): bool
    {
        $minutes = max(15, $appointment->starts_at->diffInMinutes($appointment->ends_at));
        $vetId = $appointment->users()->wherePivot('role', 'primary')->value('users.id') ?? $appointment->users()->value('users.id');

        return collect(($this->slots)($clinic, $appointment->branch_id, $start->toDateString(), $minutes, $vetId))
            ->flatMap(fn ($row) => $row['slots'])
            ->contains(fn ($slot) => $slot['starts_at']->equalTo($start->copy()->utc()));
    }

    /** Free text (or anything the bot doesn't understand): to the clinic's inbox. */
    private function handOff(Clinic $clinic, WhatsAppConversation $conversation, InboundMessage $in, ?Owner $owner): void
    {
        $inbox = Subscription::allows('whatsapp_inbox');
        $lastAck = data_get($conversation->context, 'ack_at');

        if ($inbox) {
            $conversation->update([
                'state' => WhatsAppConversation::HANDOFF,
                'needs_attention_since' => $conversation->needs_attention_since ?? now(),
            ]);
        }

        if ($lastAck === null || Carbon::parse($lastAck)->lt(now()->subMinutes(self::ACK_EVERY_MINUTES))) {
            $name = $owner ? ' '.Str::of($owner->full_name)->explode(' ')->first() : '';
            $this->replier->reply($clinic->id, $in->from, $owner?->id, $inbox
                ? "Gracias{$name}, recibimos tu mensaje. {$clinic->name} te responderá pronto por este mismo chat."
                : "Gracias{$name}. Por este número solo enviamos recordatorios. {$this->contactClinic($clinic)}");
            $conversation->update(['context' => array_merge((array) $conversation->context, ['ack_at' => now()->toIso8601String()])]);
        }
    }

    /** "Para otras consultas escríbele a Veterinaria Anipet: https://wa.me/57…" (its own number). */
    private function contactClinic(Clinic $clinic): string
    {
        $digits = preg_replace('/\D/', '', (string) $clinic->phone);

        if (strlen($digits) === 10) {
            $digits = '57'.$digits; // Colombian mobile without country code
        }

        return strlen($digits) >= 11
            ? "Para otras consultas escríbele directamente a {$clinic->name}: https://wa.me/{$digits}"
            : "Para otras consultas comunícate directamente con {$clinic->name}.";
    }

    private function flag(string $clinicId, ?string $phone): void
    {
        if ($phone !== null) {
            WhatsAppConversation::where('phone', $phone)->where('active_clinic_id', $clinicId)->whereNull('needs_attention_since')->update(['needs_attention_since' => now(), 'state' => WhatsAppConversation::HANDOFF]);
        }
    }

    /** Ends with one period, not two after "a. m." */
    private function sentence(string $text): string
    {
        return str_ends_with($text, '.') ? $text : $text.'.';
    }

    /** "el martes 6 de octubre a las 9:30 a. m." */
    private function when(CarbonInterface $utc, Clinic $clinic): string
    {
        $local = Carbon::instance($utc)->setTimezone($clinic->timezone);

        return 'el '.$local->translatedFormat('l j \d\e F').' a las '.$local->format('g:i').($local->hour < 12 ? ' a. m.' : ' p. m.');
    }

    /** Button title ≤ 20 chars: "Mié 7 oct, 9:30am" */
    private function shortSlot(Carbon $utc, Clinic $clinic): string
    {
        $local = $utc->copy()->setTimezone($clinic->timezone);

        return Str::ucfirst(rtrim($local->translatedFormat('D'), '.')).' '.$local->format('j').' '.rtrim($local->translatedFormat('M'), '.').', '.$local->format('g:i').($local->hour < 12 ? 'am' : 'pm');
    }
}
