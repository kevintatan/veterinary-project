<?php

use App\Actions\Patients\RecordConsents;
use App\Auth\ClinicRole;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Message;
use App\Models\Plan;
use App\Models\ReminderRule;
use App\Models\Subscription;
use App\Models\WhatsAppConversation;
use Illuminate\Support\Carbon;

const BOT_TZ = 'America/Bogota';

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-10-05 07:00', BOT_TZ)); // Monday

    [$this->clinic, $this->admin] = clinicWithMember();
    [, $this->vet] = clinicWithMember(role: ClinicRole::Veterinarian, clinic: $this->clinic);
    $this->clinic->update(['name' => 'Veterinaria Anipet']);
    $this->token = clinicToken($this->admin, $this->clinic);
    tenant()->runAs($this->clinic->id, fn () => ReminderRule::createDefaults());
    $this->branch = tenant()->runAs($this->clinic->id, fn () => Branch::first());

    // Mon–Fri 08:00–12:00 for the whole branch.
    $this->freshRequest()->withToken($this->token)->putJson('/api/v1/schedules', [
        'branch_id' => $this->branch->id,
        'ranges' => array_map(fn ($d) => ['weekday' => $d, 'start_time' => '08:00', 'end_time' => '12:00'], [1, 2, 3, 4, 5]),
    ])->assertOk();

    $this->owner = makeOwner($this->clinic, ['full_name' => 'Luz Marina Pérez', 'mobile_phone' => '+573123334455']);
    $this->pet = makePet($this->owner, ['name' => 'Kira']);
    tenant()->runAs($this->clinic->id, fn () => app(RecordConsents::class)($this->owner, ['data_processing' => true, 'whatsapp' => true], 'in_person', null));

    $this->appointment = tenant()->runAs($this->clinic->id, function () {
        $start = Carbon::parse('2026-10-06 09:30', BOT_TZ);
        $a = Appointment::create([
            'branch_id' => $this->branch->id, 'pet_id' => $this->pet->id, 'owner_id' => $this->owner->id,
            'starts_at' => $start, 'ends_at' => $start->copy()->addMinutes(30), 'status' => 'scheduled',
        ]);
        $a->users()->attach($this->vet->id, ['clinic_id' => $this->clinic->id, 'role' => 'primary']);

        return $a;
    });

    // The reminder goes out (fake provider) with its three buttons.
    Carbon::setTestNow(Carbon::parse('2026-10-05 09:31', BOT_TZ));
    $this->artisan('reminders:dispatch');
    $this->reminderMessage = tenant()->runAs($this->clinic->id, fn () => Message::where('purpose', 'appointment_reminder')->sole());
});

function ownerTaps(string $payload): void
{
    Carbon::setTestNow(now()->addSeconds(5)); // the clock is frozen: keep messages in order
    test()->freshRequest()->withToken(test()->token)
        ->postJson('/api/v1/dev/whatsapp/simulate', ['phone' => '+573123334455', 'payload' => $payload])
        ->assertOk();
}

/** The chat with the team (inbox) is a higher-plan feature; the default test clinic is on Pro. */
function withInbox(): void
{
    tenant()->runAs(test()->clinic->id, fn () => Subscription::query()->update(['plan_id' => Plan::where('code', 'pro_invoicing')->value('id')]));
}

function lastBotReply(): Message
{
    return tenant()->runAs(test()->clinic->id, fn () => Message::where('purpose', 'bot_reply')->latest('created_at')->latest('id')->first());
}

it('sends the reminder with Confirmar / Reprogramar / Cancelar buttons pointing to it', function () {
    $id = $this->reminderMessage->id;

    expect($this->reminderMessage->payload['buttons'])->toBe(["confirm:{$id}", "reschedule:{$id}", "cancel:{$id}"]);
});

it('confirms the appointment from the button', function () {
    ownerTaps("confirm:{$this->reminderMessage->id}");

    $appointment = tenant()->runAs($this->clinic->id, fn () => $this->appointment->fresh(['statusChanges']));
    expect($appointment->status->value)->toBe('confirmed')
        ->and($appointment->statusChanges->last()->channel)->toBe('whatsapp')
        ->and(lastBotReply()->body)->toBe('¡Gracias! Confirmamos la cita de Kira el martes 6 de octubre a las 9:30 a. m. Te esperamos en Veterinaria Anipet.');
});

it('asks before cancelling and cancels with a reason', function () {
    ownerTaps("cancel:{$this->reminderMessage->id}");
    $question = lastBotReply();
    expect($question->body)->toStartWith('¿Seguro que quieres cancelar la cita de Kira')
        ->and(collect($question->payload['buttons'])->pluck(1)->all())->toBe(['Sí, cancelar', 'No, la mantengo']);
    expect(tenant()->runAs($this->clinic->id, fn () => $this->appointment->fresh()->status->value))->toBe('scheduled');

    ownerTaps($question->payload['buttons'][0][0]); // "Sí, cancelar"

    $appointment = tenant()->runAs($this->clinic->id, fn () => $this->appointment->fresh());
    expect($appointment->status->value)->toBe('cancelled')
        ->and($appointment->cancellation_reason)->toBe('Cancelada por el tutor por WhatsApp');
});

it('offers real free slots on different days and moves the appointment to the one chosen', function () {
    ownerTaps("reschedule:{$this->reminderMessage->id}");
    $offer = lastBotReply();
    $titles = collect($offer->payload['buttons'])->pluck(1)->all();

    expect($titles)->toHaveCount(3)
        ->and($titles)->toBe(['Mar 6 oct, 8:00am', 'Mié 7 oct, 8:00am', 'Jue 8 oct, 8:00am']) // today's last slot (11:30) is < 2 h away
        ->and(collect($titles)->every(fn ($t) => mb_strlen($t) <= 20))->toBeTrue();

    ownerTaps($offer->payload['buttons'][0][0]);

    $appointment = tenant()->runAs($this->clinic->id, fn () => $this->appointment->fresh());
    expect($appointment->starts_at->setTimezone(BOT_TZ)->format('Y-m-d H:i'))->toBe('2026-10-06 08:00')
        ->and(lastBotReply()->body)->toContain('movimos la cita de Kira el martes 6 de octubre a las 8:00 a. m.');
});

it('hands free text to the clinic inbox, acknowledges once and lets staff reply', function () {
    withInbox();
    $this->freshRequest()->withToken($this->token)->postJson('/api/v1/dev/whatsapp/simulate', ['phone' => '+573123334455', 'text' => '¿Puedo llevarla sin ayuno?'])->assertOk();
    Carbon::setTestNow(now()->addMinute());
    $this->freshRequest()->withToken($this->token)->postJson('/api/v1/dev/whatsapp/simulate', ['phone' => '+573123334455', 'text' => 'Gracias'])->assertOk();

    expect(tenant()->runAs($this->clinic->id, fn () => Message::where('purpose', 'bot_reply')->count()))->toBe(1)
        ->and(lastBotReply()->body)->toBe('Gracias Luz, recibimos tu mensaje. Veterinaria Anipet te responderá pronto por este mismo chat.');

    $inbox = $this->freshRequest()->withToken($this->token)->getJson('/api/v1/conversations')->assertOk()->json('data');
    expect($inbox)->toHaveCount(1)->and($inbox[0]['owner']['full_name'])->toBe('Luz Marina Pérez')->and($inbox[0]['window_open'])->toBeTrue();

    Carbon::setTestNow(now()->addMinute());
    $thread = $this->freshRequest()->withToken($this->token)
        ->postJson("/api/v1/conversations/{$inbox[0]['id']}/reply", ['text' => 'Sí, puede comer algo liviano.'])
        ->assertOk()
        ->json('data');
    expect(collect($thread['messages'])->last()['sent_by'])->toBe($this->admin->name)
        ->and($thread['needs_attention_since'])->toBeNull();

    // After 24 h without news from the owner, WhatsApp only allows templates.
    Carbon::setTestNow(now()->addHours(25));
    $this->freshRequest()->withToken($this->token)
        ->postJson("/api/v1/conversations/{$inbox[0]['id']}/reply", ['text' => '¿Cómo sigue?'])
        ->assertJsonValidationErrors('text');
});

it('verifies Meta signatures and processes delivery statuses and button replies from the real webhook', function () {
    config(['whatsapp.meta.app_secret' => 'secret', 'whatsapp.meta.verify_token' => 'verify-me']);

    $this->get('/api/v1/webhooks/whatsapp?hub.mode=subscribe&hub.verify_token=verify-me&hub.challenge=12345')->assertOk()->assertSee('12345');
    $this->get('/api/v1/webhooks/whatsapp?hub.mode=subscribe&hub.verify_token=nope&hub.challenge=1')->assertForbidden();

    $payload = ['entry' => [['changes' => [['value' => [
        'statuses' => [['id' => $this->reminderMessage->provider_message_id, 'status' => 'read', 'timestamp' => (string) now()->timestamp]],
        'messages' => [['from' => '573123334455', 'id' => 'wamid.IN1', 'type' => 'button', 'button' => ['payload' => "confirm:{$this->reminderMessage->id}", 'text' => 'Confirmar']]],
    ]]]]]];
    $body = json_encode($payload);

    $this->call('POST', '/api/v1/webhooks/whatsapp', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_HUB_SIGNATURE_256' => 'sha256=bad'], $body)->assertUnauthorized();
    $this->call('POST', '/api/v1/webhooks/whatsapp', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $body, 'secret')], $body)->assertOk();
    // Meta retries: the same message twice must be processed once.
    $this->call('POST', '/api/v1/webhooks/whatsapp', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $body, 'secret')], $body)->assertOk();

    tenant()->runAs($this->clinic->id, function () {
        expect($this->reminderMessage->fresh()->status)->toBe('read')
            ->and($this->appointment->fresh()->status->value)->toBe('confirmed')
            ->and(Message::where('direction', 'inbound')->count())->toBe(1);
    });
});

it('points to the clinic own WhatsApp when the plan has no inbox', function () {
    $this->clinic->update(['phone' => '3157778899']);
    $this->freshRequest()->withToken($this->token)->postJson('/api/v1/dev/whatsapp/simulate', ['phone' => '+573123334455', 'text' => '¿Venden concentrado?'])->assertOk();

    expect(lastBotReply()->body)->toBe('Gracias Luz. Por este número solo enviamos recordatorios. Para otras consultas escríbele directamente a Veterinaria Anipet: https://wa.me/573157778899')
        ->and(WhatsAppConversation::sole()->needs_attention_since)->toBeNull();

    $inbox = $this->freshRequest()->withToken($this->token)->getJson('/api/v1/conversations')->assertOk();
    expect($inbox->json('meta.enabled'))->toBeFalse()->and($inbox->json('data'))->toBe([]);
    $this->freshRequest()->withToken($this->token)->getJson('/api/v1/conversations/'.WhatsAppConversation::sole()->id)->assertForbidden();

    // The buttons keep working on every plan with automatic reminders.
    ownerTaps("confirm:{$this->reminderMessage->id}");
    expect(tenant()->runAs($this->clinic->id, fn () => $this->appointment->fresh()->status->value))->toBe('confirmed');
});

it('keeps conversations inside their clinic', function () {
    withInbox();
    $this->freshRequest()->withToken($this->token)->postJson('/api/v1/dev/whatsapp/simulate', ['phone' => '+573123334455', 'text' => 'Hola'])->assertOk();
    $conversation = WhatsAppConversation::sole();

    [$other, $stranger] = clinicWithMember();
    $otherToken = clinicToken($stranger, $other);
    tenant()->runAs($other->id, fn () => Subscription::query()->update(['plan_id' => Plan::where('code', 'pro_invoicing')->value('id')]));

    $this->freshRequest()->withToken($otherToken)->getJson('/api/v1/conversations')->assertJsonCount(0, 'data');
    $this->freshRequest()->withToken($otherToken)->getJson("/api/v1/conversations/{$conversation->id}")->assertNotFound();
    $this->freshRequest()->withToken($otherToken)->postJson('/api/v1/dev/whatsapp/simulate', ['phone' => '+573123334455', 'text' => 'x'])->assertNotFound();
});
