<?php

use App\Actions\Patients\RecordConsents;
use App\Auth\ClinicRole;
use App\Messaging\Reminders\ReminderPlanner;
use App\Messaging\WhatsApp\OutgoingMessage;
use App\Messaging\WhatsApp\SendResult;
use App\Messaging\WhatsApp\WhatsAppException;
use App\Messaging\WhatsApp\WhatsAppProvider;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Message;
use App\Models\Plan;
use App\Models\Reminder;
use App\Models\ReminderRule;
use App\Models\Subscription;
use App\Models\UsageCounter;
use Illuminate\Support\Carbon;

const BOGOTA = 'America/Bogota';

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-10-05 07:00', BOGOTA)); // Monday

    [$this->clinic, $this->admin] = clinicWithMember();
    [, $this->vet] = clinicWithMember(role: ClinicRole::Veterinarian, clinic: $this->clinic);
    $this->clinic->update(['name' => 'Veterinaria Anipet']);
    tenant()->runAs($this->clinic->id, fn () => ReminderRule::createDefaults());

    $this->owner = makeOwner($this->clinic, ['full_name' => 'Luz Marina Pérez', 'mobile_phone' => '+573123334455']);
    $this->pet = makePet($this->owner, ['name' => 'Kira']);
    tenant()->runAs($this->clinic->id, fn () => app(RecordConsents::class)($this->owner, ['data_processing' => true, 'whatsapp' => true], 'in_person', null));
});

function arBook(string $local, string $status = 'scheduled'): Appointment
{
    return tenant()->runAs(test()->clinic->id, function () use ($local, $status) {
        $start = Carbon::parse($local, BOGOTA);

        return Appointment::create([
            'branch_id' => Branch::first()->id, 'pet_id' => test()->pet->id, 'owner_id' => test()->owner->id,
            'starts_at' => $start, 'ends_at' => $start->copy()->addMinutes(30), 'status' => $status,
        ]);
    });
}

function arReminders($subject)
{
    return tenant()->runAs(test()->clinic->id, fn () => Reminder::where('remindable_id', $subject->id)->orderBy('created_at')->get());
}

function arLocal(Reminder $reminder): string
{
    return $reminder->scheduled_for->copy()->setTimezone(BOGOTA)->format('Y-m-d H:i');
}

function arRunAt(string $local): void
{
    Carbon::setTestNow(Carbon::parse($local, BOGOTA));
    test()->artisan('reminders:dispatch')->assertSuccessful();
}

function arVaccinate(string $occurredAt, string $nextControl, string $vaccine = 'Rabia'): string
{
    return test()->freshRequest()->withToken(clinicToken(test()->vet, test()->clinic))
        ->postJson('/api/v1/pets/'.test()->pet->id.'/clinical-records', [
            'type' => 'vaccination', 'occurred_at' => $occurredAt, 'next_control_at' => $nextControl,
            'vaccination' => ['vaccine_name' => $vaccine],
        ])->assertCreated()->json('data.id');
}

// ---------------------------------------------------------------- planning

it('plans the reminder 24 h before the appointment', function () {
    $appointment = arBook('2026-10-06 09:30');

    $reminders = arReminders($appointment);
    expect($reminders)->toHaveCount(1)
        ->and($reminders[0]->status)->toBe('pending')
        ->and(arLocal($reminders[0]))->toBe('2026-10-05 09:30');
});

it('never sends at night: moves it into the 8:00–19:00 window', function () {
    expect(arLocal(arReminders(arBook('2026-10-07 07:30'))[0]))->toBe('2026-10-06 08:00')
        ->and(arLocal(arReminders(arBook('2026-10-07 20:30'))[0]))->toBe('2026-10-06 19:00');
});

it('skips appointments booked at the last minute and sends late ones as soon as possible', function () {
    expect(arReminders(arBook('2026-10-05 09:00')))->toHaveCount(0); // booked 2 h before

    Carbon::setTestNow(Carbon::parse('2026-10-05 15:00', BOGOTA));
    expect(arLocal(arReminders(arBook('2026-10-06 10:00'))[0]))->toBe('2026-10-05 15:00'); // 19 h ahead: now
});

it('moves the reminder when the appointment is rescheduled and cancels it when the appointment is cancelled', function () {
    $appointment = arBook('2026-10-06 09:30');
    tenant()->runAs($this->clinic->id, fn () => $appointment->update(['starts_at' => Carbon::parse('2026-10-08 11:00', BOGOTA), 'ends_at' => Carbon::parse('2026-10-08 11:30', BOGOTA)]));

    $reminders = arReminders($appointment);
    expect($reminders)->toHaveCount(1)->and(arLocal($reminders[0]))->toBe('2026-10-07 11:00');

    tenant()->runAs($this->clinic->id, fn () => $appointment->update(['status' => 'cancelled']));
    expect(arReminders($appointment)[0]->status)->toBe('cancelled');
});

it('plans vaccine boosters 7 days before', function () {
    $id = arVaccinate('2025-10-20 10:00', '2026-10-20');

    $reminders = tenant()->runAs($this->clinic->id, fn () => Reminder::where('remindable_id', $id)->get());
    expect($reminders)->toHaveCount(1)->and(arLocal($reminders[0]))->toBe('2026-10-13 09:00');
});

it('cancels pending reminders when the clinic turns a rule off', function () {
    $appointment = arBook('2026-10-06 09:30');
    tenant()->runAs($this->clinic->id, function () {
        ReminderRule::where('trigger', 'appointment')->update(['is_active' => false]);
        app(ReminderPlanner::class)->replanClinic();
    });

    expect(arReminders($appointment)[0]->status)->toBe('cancelled');
});

// ---------------------------------------------------------------- sending

it('sends due reminders once, logs them and counts the usage', function () {
    $appointment = arBook('2026-10-06 09:30');

    arRunAt('2026-10-05 09:29');
    expect(arReminders($appointment)[0]->status)->toBe('pending');

    arRunAt('2026-10-05 09:31');
    arRunAt('2026-10-05 09:32'); // a second run must not send it again

    $reminder = arReminders($appointment)[0];
    $messages = tenant()->runAs($this->clinic->id, fn () => Message::all());

    expect($reminder->status)->toBe('sent')
        ->and($messages)->toHaveCount(1)
        ->and($messages[0]->provider)->toBe('fake')
        ->and($messages[0]->body)->toBe('Hola Luz, te escribimos de *Veterinaria Anipet*. Te recordamos que *Kira* tiene cita a las 9:30 a. m. del martes 6 de octubre. ¿Nos confirmas tu asistencia? Usa los botones de abajo. ¡Gracias!')
        ->and($messages[0]->payload['parameters'])->toBe(['Luz', 'Veterinaria Anipet', 'Kira', 'a las 9:30 a. m. del martes 6 de octubre'])
        ->and($reminder->message_id)->toBe($messages[0]->id)
        ->and(tenant()->runAs($this->clinic->id, fn () => UsageCounter::used('whatsapp_utility')))->toBe(1);
});

it('checks consent, death and newer vaccines again right before sending', function () {
    $appointment = arBook('2026-10-06 09:30');
    tenant()->runAs($this->clinic->id, fn () => app(RecordConsents::class)($this->owner, ['whatsapp' => false], 'in_person', null));
    arRunAt('2026-10-05 09:31');
    expect(arReminders($appointment)[0]->status)->toBe('skipped_no_consent');

    tenant()->runAs($this->clinic->id, fn () => app(RecordConsents::class)($this->owner, ['whatsapp' => true], 'in_person', null));
    Carbon::setTestNow(Carbon::parse('2026-10-05 07:00', BOGOTA));
    $old = arVaccinate('2025-10-20 10:00', '2026-10-20');
    arVaccinate('2026-10-05 06:00', '2027-10-05'); // the booster was already applied
    arRunAt('2026-10-13 09:01');
    expect(tenant()->runAs($this->clinic->id, fn () => Reminder::where('remindable_id', $old)->value('status')))->toBe('skipped_answered');

    $later = arBook('2026-10-20 10:00');
    tenant()->runAs($this->clinic->id, fn () => $this->pet->update(['deceased_at' => '2026-10-14']));
    arRunAt('2026-10-19 10:01');
    expect(arReminders($later)[0]->status)->toBe('skipped_deceased');
});

it('skips the vaccine reminder when the pet already has an appointment, and sends it if that appointment is cancelled', function () {
    Carbon::setTestNow(Carbon::parse('2026-10-05 07:00', BOGOTA));
    $record = arVaccinate('2025-10-20 10:00', '2026-10-20');
    $appointment = arBook('2026-10-16 10:00'); // "Michi": they already booked the booster

    arRunAt('2026-10-13 09:01');
    $reminder = tenant()->runAs($this->clinic->id, fn () => Reminder::where('remindable_id', $record)->sole());
    expect($reminder->status)->toBe('skipped_has_appointment')
        ->and($reminder->toArray()['status_label'] ?? Reminder::SKIPPED[$reminder->status])->toBe('La mascota ya tiene una cita agendada');

    // Without that appointment the same rule sends it.
    Carbon::setTestNow(Carbon::parse('2026-10-05 07:00', BOGOTA));
    $other = arVaccinate('2025-10-21 10:00', '2026-10-21', 'Moquillo');
    tenant()->runAs($this->clinic->id, fn () => $appointment->update(['status' => 'cancelled']));
    arRunAt('2026-10-14 09:01');
    expect(tenant()->runAs($this->clinic->id, fn () => Reminder::where('remindable_id', $other)->value('status')))->toBe('sent');
});

it('respects the plan: no automatic reminders on Básico, and the monthly limit on Pro', function () {
    $appointment = arBook('2026-10-06 09:30');
    tenant()->runAs($this->clinic->id, fn () => UsageCounter::add($this->clinic->id, 'whatsapp_utility', 500));
    arRunAt('2026-10-05 09:31');
    expect(arReminders($appointment)[0]->status)->toBe('skipped_limit');

    $next = arBook('2026-10-08 09:30');
    tenant()->runAs($this->clinic->id, fn () => Subscription::query()->update(['plan_id' => Plan::where('features->whatsapp_bot', false)->value('id')]));
    arRunAt('2026-10-07 09:31');
    expect(arReminders($next)[0]->status)->toBe('skipped_plan');
});

it('retries temporary provider errors and gives up after 3 attempts', function () {
    $this->app->bind(WhatsAppProvider::class, fn () => new class implements WhatsAppProvider
    {
        public function send(OutgoingMessage $message): SendResult
        {
            throw new WhatsAppException('Meta 503: temporarily unavailable');
        }

        public function sendText(string $to, string $body): SendResult
        {
            throw new WhatsAppException('Meta 503: temporarily unavailable');
        }

        public function sendButtons(string $to, string $body, array $buttons): SendResult
        {
            throw new WhatsAppException('Meta 503: temporarily unavailable');
        }

        public function name(): string
        {
            return 'meta';
        }
    });

    $appointment = arBook('2026-10-06 09:30');
    arRunAt('2026-10-05 09:31');
    $reminder = arReminders($appointment)[0];
    expect($reminder->status)->toBe('pending')->and($reminder->attempts)->toBe(1)->and(arLocal($reminder))->toBe('2026-10-05 09:36');

    arRunAt('2026-10-05 09:37');
    arRunAt('2026-10-05 10:08');
    $reminder = arReminders($appointment)[0];
    expect($reminder->status)->toBe('failed')->and($reminder->attempts)->toBe(3)->and($reminder->last_error)->toContain('503');
});

it('keeps each clinic in its lane', function () {
    arBook('2026-10-06 09:30');
    [$other] = clinicWithMember();

    arRunAt('2026-10-05 09:31');

    expect(tenant()->runAs($other->id, fn () => Message::count()))->toBe(0)
        ->and(tenant()->runAs($this->clinic->id, fn () => Message::count()))->toBe(1);
});
