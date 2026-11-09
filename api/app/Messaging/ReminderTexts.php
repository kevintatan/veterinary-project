<?php

namespace App\Messaging;

use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Clinic;
use App\Models\Clinical\ClinicalRecord;
use Carbon\CarbonInterface;
use Illuminate\Support\Str;

/**
 * Reminder wording, in one place, for every channel: the manual wa.me links
 * (4a) and the automatic WhatsApp templates (4c/4d). Each text is built from
 * the same variables Meta's approved template receives ({{1}}, {{2}}…), so
 * what staff send by hand and what goes out automatically read the same.
 *
 * The approved template bodies are in docs/whatsapp.md §3. Meta rejects empty
 * variables: every parameter here always has content.
 */
class ReminderTexts
{
    /** "Hola {{1}}, te escribimos de *{{2}}*. Te recordamos que *{{3}}* tiene cita {{4}}. Si no puedes asistir…" */
    public const APPOINTMENT = 'Hola {{1}}, te escribimos de *{{2}}*. Te recordamos que *{{3}}* tiene cita {{4}}. Si no puedes asistir, por favor avísanos respondiendo este mensaje. ¡Gracias!';

    /**
     * Automatic template (4d): same variables, with the Confirmar / Reprogramar /
     * Cancelar quick-reply buttons below it.
     */
    public const APPOINTMENT_WITH_BUTTONS = 'Hola {{1}}, te escribimos de *{{2}}*. Te recordamos que *{{3}}* tiene cita {{4}}. ¿Nos confirmas tu asistencia? Usa los botones de abajo. ¡Gracias!';

    /** "Hola {{1}}, te escribimos de *{{2}}*. A *{{3}}* le corresponde {{4}} el {{5}}. ¿Te ayudamos…" */
    public const NEXT_CONTROL = 'Hola {{1}}, te escribimos de *{{2}}*. A *{{3}}* le corresponde {{4}} el {{5}}. ¿Te ayudamos a agendar la cita? Responde este mensaje. ¡Gracias!';

    /** Online booking (docs/reservas.md, B2). Button: "Cancelar solicitud". */
    public const BOOKING_RECEIVED = 'Hola {{1}}, recibimos tu solicitud de cita en *{{2}}* para *{{3}}*: {{4}}. La clínica la revisará y te responderá por aquí. *Tu cita aún no está confirmada.*';

    /** Buttons: "Reprogramar", "Cancelar". */
    public const BOOKING_ACCEPTED = '¡Hola {{1}}! *{{2}}* aceptó tu solicitud: *{{3}}* tiene cita {{4}}. Te enviaremos un recordatorio el día anterior. ¡Te esperamos!';

    public const BOOKING_REJECTED = 'Hola {{1}}, *{{2}}* no pudo aceptar tu solicitud de cita para *{{3}}* {{4}}. Motivo: {{5}}. Puedes pedir otra hora aquí: {{6}}';

    /** @param 'booking_received'|'booking_accepted'|'booking_rejected' $purpose */
    public function booking(string $purpose, Appointment $appointment, Clinic $clinic): ReminderContent
    {
        $appointment->loadMissing(['owner', 'pet', 'service', 'branch']);
        $details = $appointment->booking_details ?? [];
        $start = $appointment->starts_at->copy()->setTimezone($clinic->timezone);
        $service = $appointment->service ? Str::lower($appointment->service->name).' ' : '';
        // Date last, like the reminders: a sentence ending in "a. m." would read "a. m.." in the template.
        $when = "{$service}a las {$this->time($start)} del {$start->translatedFormat('l j \d\e F')}";

        $parameters = [
            $this->firstName($appointment->owner?->full_name ?? ($details['name'] ?? null)),
            $this->clinicName($clinic, $appointment->branch),
            (string) ($appointment->pet?->name ?? ($details['pet_name'] ?? '')),
            $when,
        ];

        return match ($purpose) {
            'booking_received' => $this->content($purpose, self::BOOKING_RECEIVED, $parameters),
            'booking_accepted' => $this->content($purpose, self::BOOKING_ACCEPTED, $parameters),
            'booking_rejected' => $this->content($purpose, self::BOOKING_REJECTED, [
                ...$parameters,
                rtrim((string) $appointment->cancellation_reason, '.'),
                rtrim((string) config('app.frontend_url'), '/').'/reservar/'.$clinic->slug,
            ]),
        };
    }

    public function appointment(Appointment $appointment, Clinic $clinic, bool $withButtons = false): ReminderContent
    {
        $appointment->loadMissing(['owner', 'pet', 'service', 'branch']);
        $start = $appointment->starts_at->copy()->setTimezone($clinic->timezone);
        $day = $start->translatedFormat('l j \d\e F');
        $service = $appointment->service ? 'de '.Str::lower($appointment->service->name).' ' : '';

        $when = $appointment->is_time_unset
            ? "{$service}el {$day} (por orden de llegada)"
            : "{$service}a las {$this->time($start)} del {$day}";

        return $this->content('appointment_reminder', $withButtons ? self::APPOINTMENT_WITH_BUTTONS : self::APPOINTMENT, [
            $this->firstName($appointment->owner?->full_name),
            $this->clinicName($clinic, $appointment->branch),
            (string) $appointment->pet?->name,
            $when,
        ]);
    }

    public function nextControl(ClinicalRecord $record, Clinic $clinic): ReminderContent
    {
        $record->loadMissing(['pet.owner', 'vaccination', 'deworming']);

        $what = match ($record->type) {
            'vaccination' => 'el refuerzo de la vacuna '.($record->vaccination?->vaccine_name ?? ''),
            'deworming' => 'su próxima desparasitación',
            default => 'su control'.($record->next_control_note ? " ({$record->next_control_note})" : ''),
        };

        return $this->content('next_control', self::NEXT_CONTROL, [
            $this->firstName($record->pet?->owner?->full_name),
            $this->clinicName($clinic, null),
            (string) $record->pet?->name,
            trim($what),
            $record->next_control_at->translatedFormat('j \d\e F'),
        ]);
    }

    /** @param list<string> $parameters */
    private function content(string $purpose, string $template, array $parameters): ReminderContent
    {
        $parameters = array_map(fn (string $p) => trim($p) === '' ? '-' : trim($p), $parameters);
        $body = preg_replace_callback('/\{\{(\d+)\}\}/', fn ($m) => $parameters[(int) $m[1] - 1] ?? '', $template);

        return new ReminderContent($purpose, $body, $parameters);
    }

    /** "Veterinaria Anipet (Sede Cabecera)" when the clinic has several branches. */
    private function clinicName(Clinic $clinic, ?Branch $branch): string
    {
        return $branch !== null && Branch::count() > 1 ? "{$clinic->name} ({$branch->name})" : $clinic->name;
    }

    private function firstName(?string $fullName): string
    {
        return Str::of((string) $fullName)->trim()->explode(' ')->first() ?: 'hola';
    }

    /** 9:30 a. m. — the Colombian way of writing times. */
    private function time(CarbonInterface $at): string
    {
        return $at->format('g:i').($at->hour < 12 ? ' a. m.' : ' p. m.');
    }
}
