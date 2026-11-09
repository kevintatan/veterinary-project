<?php

use App\Auth\ClinicRole;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Catalogs\Species;
use App\Models\Message;
use App\Models\Owner;
use App\Models\Plan;
use App\Models\Service;
use App\Models\StaffNotification;
use App\Models\Subscription;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-10-05 07:00', 'America/Bogota')); // Monday

    [$this->clinic, $this->admin] = clinicWithMember();
    [, $this->vet] = clinicWithMember(role: ClinicRole::Veterinarian, clinic: $this->clinic);
    [, $this->receptionist] = clinicWithMember(role: ClinicRole::Receptionist, clinic: $this->clinic);
    $this->token = clinicToken($this->admin, $this->clinic);

    inClinic($this->clinic);
    $this->branch = Branch::first();
    $this->vaccine = Service::create(['name' => 'Vacunación', 'category' => 'vaccination', 'duration_minutes' => 30, 'is_bookable_online' => true]);
    $this->internal = Service::create(['name' => 'Cirugía', 'category' => 'surgery', 'duration_minutes' => 60, 'is_bookable_online' => false]);
    $this->canine = Species::where('code', 'canine')->value('id');
    tenant()->forget();

    // Monday 08:00–10:00 for everyone in the branch.
    $this->freshRequest()->withToken($this->token)->putJson('/api/v1/schedules', [
        'branch_id' => $this->branch->id,
        'ranges' => [['weekday' => 1, 'start_time' => '08:00', 'end_time' => '10:00']],
    ])->assertOk();

    $this->freshRequest()->withToken($this->token)->putJson('/api/v1/online-booking/settings', [
        'enabled' => true, 'min_notice_hours' => 2, 'max_days_ahead' => 30, 'choose_professional' => true, 'message' => 'Urgencias: llama al 6076432210',
    ])->assertOk()->assertJsonPath('data.public_path', '/reservar/'.$this->clinic->slug);
});

function bookingPayload(array $overrides = []): array
{
    return array_merge([
        'branch_id' => test()->branch->id,
        'service_id' => test()->vaccine->id,
        'starts_at' => '2026-10-05T09:30',
        'name' => 'Paola Díaz',
        'phone' => '318 666 5544',
        'document_type' => 'CC',
        'document_number' => '1.098.765.432',
        'pet_name' => 'Rocky',
        'species_id' => test()->canine,
        'notes' => 'Le toca el refuerzo',
        'accept_data' => true,
        'accept_whatsapp' => true,
    ], $overrides);
}

function bookingUrl(string $path = ''): string
{
    return '/api/v1/public/booking/'.test()->clinic->slug.$path;
}

it('shows the public booking page only when enabled and included in the plan', function () {
    $this->getJson(bookingUrl())->assertOk()
        ->assertJsonPath('data.clinic.name', $this->clinic->name)
        ->assertJsonPath('data.message', 'Urgencias: llama al 6076432210')
        ->assertJsonCount(1, 'data.services') // only bookable services
        ->assertJsonPath('data.services.0.name', 'Vacunación');

    // Básico doesn't include online booking.
    tenant()->runAs($this->clinic->id, fn () => Subscription::first()->update(['plan_id' => Plan::where('code', 'basic')->value('id'), 'status' => 'active', 'current_period_end' => now()->addMonth()]));
    $this->getJson(bookingUrl())->assertNotFound();

    $this->getJson('/api/v1/public/booking/no-existe')->assertNotFound();
});

it('offers free times respecting the minimum notice', function () {
    // 07:00 now + 2 h notice → from 09:00; slots every 30 min until 10:00.
    $this->getJson(bookingUrl('/slots?'.http_build_query(['branch_id' => $this->branch->id, 'service_id' => $this->vaccine->id, 'date' => '2026-10-05'])))
        ->assertOk()
        ->assertJsonPath('data', ['2026-10-05T09:00:00-05:00', '2026-10-05T09:30:00-05:00']);

    // Not beyond the horizon (30 days).
    $this->getJson(bookingUrl('/slots?'.http_build_query(['branch_id' => $this->branch->id, 'service_id' => $this->vaccine->id, 'date' => '2026-11-16'])))
        ->assertOk()->assertJsonPath('data', []);
});

it('creates a request that holds the slot, gets no reminders and notifies the people who manage appointments', function () {
    $this->postJson(bookingUrl(), bookingPayload(['user_id' => $this->vet->id]))->assertCreated();

    $request = tenant()->runAs($this->clinic->id, fn () => Appointment::sole());
    expect($request->status->value)->toBe('requested')
        ->and($request->source)->toBe('online_booking')
        ->and($request->owner_id)->toBeNull()
        ->and($request->booking_details['phone'])->toBe('+573186665544')
        ->and(tenant()->runAs($this->clinic->id, fn () => Owner::count()))->toBe(0); // nothing created until accepted

    // The vet's 09:30 is taken; other professionals still offer it.
    $times = fn (?string $userId) => $this->getJson(bookingUrl('/slots?'.http_build_query(array_filter(['branch_id' => $this->branch->id, 'service_id' => $this->vaccine->id, 'date' => '2026-10-05', 'user_id' => $userId]))))->json('data');
    expect($times($this->vet->id))->toBe(['2026-10-05T09:00:00-05:00'])
        ->and($times(null))->toContain('2026-10-05T09:30:00-05:00');
    $this->postJson(bookingUrl(), bookingPayload(['phone' => '3001112233', 'document_number' => '52111222', 'user_id' => $this->vet->id]))->assertJsonValidationErrors('starts_at');

    // Bell: admin and receptionist manage appointments; each has its own notification.
    $notified = tenant()->runAs($this->clinic->id, fn () => StaffNotification::where('type', 'booking_request')->pluck('user_id')->unique()->values()->all());
    expect($notified)->toContain($this->admin->id, $this->receptionist->id);

    $this->freshRequest()->withToken($this->token)->getJson('/api/v1/notifications')->assertOk()
        ->assertJsonPath('unread', 1)
        ->assertJsonPath('data.0.url', '/app/agenda/solicitudes');
    $this->freshRequest()->withToken($this->token)->postJson('/api/v1/notifications/read-all')->assertJsonPath('unread', 0);
});

it('rejects taken times, wrong data and too many pending requests from the same phone', function () {
    $this->postJson(bookingUrl(), bookingPayload(['accept_data' => false, 'phone' => '12345']))
        ->assertUnprocessable()->assertJsonValidationErrors(['accept_data', 'phone']);
    $this->postJson(bookingUrl(), bookingPayload(['starts_at' => '2026-10-05T08:00']))->assertJsonValidationErrors('starts_at'); // inside the 2 h notice
    $this->postJson(bookingUrl(), bookingPayload(['service_id' => $this->internal->id]))->assertNotFound();

    $this->postJson(bookingUrl(), bookingPayload(['starts_at' => '2026-10-05T09:00']))->assertCreated();
    $this->postJson(bookingUrl(), bookingPayload(['starts_at' => '2026-10-05T09:30']))->assertCreated();
    $this->postJson(bookingUrl(), bookingPayload(['starts_at' => '2026-10-05T09:00']))->assertJsonValidationErrors('phone');
});

it('lets the clinic accept a request creating the tutor and pet with their consents', function () {
    $id = $this->postJson(bookingUrl(), bookingPayload())->json('data.id');

    $this->freshRequest()->withToken($this->token)->getJson('/api/v1/booking-requests/count')->assertJsonPath('data.pending', 1);
    $this->freshRequest()->withToken($this->token)->getJson('/api/v1/booking-requests')->assertOk()
        ->assertJsonPath('data.0.details.pet_name', 'Rocky')
        ->assertJsonPath('data.0.suggestions', []);

    $this->freshRequest()->withToken($this->token)->postJson("/api/v1/booking-requests/{$id}/accept", [])->assertOk()->assertJsonPath('data.status', 'scheduled');

    tenant()->runAs($this->clinic->id, function () {
        $appointment = Appointment::with(['owner', 'pet'])->sole();
        expect($appointment->owner->full_name)->toBe('Paola Díaz')
            ->and($appointment->owner->mobile_phone)->toBe('+573186665544')
            ->and($appointment->pet->name)->toBe('Rocky')
            ->and($appointment->owner->currentConsents())->toMatchArray(['data_processing' => true, 'channel_whatsapp' => true]);
    });

    $this->freshRequest()->withToken($this->token)->postJson("/api/v1/booking-requests/{$id}/accept", [])->assertJsonValidationErrors('status');
});

it('suggests the existing tutor by phone (only to the clinic) and links the request to their pet', function () {
    $owner = makeOwner($this->clinic, ['full_name' => 'Paola Andrea Díaz', 'mobile_phone' => '+573186665544']);
    $rocky = makePet($owner, ['name' => 'Rocky']);
    makePet($owner, ['name' => 'Michi']);

    $id = $this->postJson(bookingUrl(), bookingPayload())->json('data.id');

    $suggestion = $this->freshRequest()->withToken($this->token)->getJson('/api/v1/booking-requests')->json('data.0.suggestions.0');
    expect($suggestion['id'])->toBe($owner->id)
        ->and(collect($suggestion['pets'])->firstWhere('same_name', true)['id'])->toBe($rocky->id);

    // A pet without its tutor is not accepted.
    $this->freshRequest()->withToken($this->token)->postJson("/api/v1/booking-requests/{$id}/accept", ['pet_id' => $rocky->id])->assertJsonValidationErrors('owner_id');

    $this->freshRequest()->withToken($this->token)->postJson("/api/v1/booking-requests/{$id}/accept", ['owner_id' => $owner->id, 'pet_id' => $rocky->id])->assertOk();
    expect(tenant()->runAs($this->clinic->id, fn () => [Owner::count(), Appointment::sole()->pet_id]))->toBe([1, $rocky->id]);
});

it('lets the clinic reject a request with a reason, freeing the slot', function () {
    $id = $this->postJson(bookingUrl(), bookingPayload())->json('data.id');

    $this->freshRequest()->withToken($this->token)->postJson("/api/v1/booking-requests/{$id}/reject", [])->assertJsonValidationErrors('reason');
    $this->freshRequest()->withToken($this->token)->postJson("/api/v1/booking-requests/{$id}/reject", ['reason' => 'No hay vacuna disponible ese día'])->assertOk();

    expect(tenant()->runAs($this->clinic->id, fn () => Appointment::sole()->cancellation_reason))->toBe('No hay vacuna disponible ese día');

    // Already answered.
    $this->freshRequest()->withToken($this->token)->postJson("/api/v1/booking-requests/{$id}/accept", [])->assertJsonValidationErrors('status');
});

it('gives "anyone available" requests to the professional with the fewest appointments that day', function () {
    $first = $this->postJson(bookingUrl(), bookingPayload(['starts_at' => '2026-10-05T09:00']))->json('data.id');
    $second = $this->postJson(bookingUrl(), bookingPayload(['starts_at' => '2026-10-05T09:30', 'phone' => '3001112233', 'document_number' => '52111222']))->json('data.id');

    $assigned = tenant()->runAs($this->clinic->id, fn () => Appointment::with('users')->findMany([$first, $second])->map(fn ($a) => $a->users->first()->id)->unique());
    expect($assigned)->toHaveCount(2);
});

it('asks for the document, saves it on the new tutor and never duplicates a tutor', function () {
    $this->postJson(bookingUrl(), bookingPayload(['document_number' => '', 'document_type' => 'NIT']))->assertJsonValidationErrors(['document_number', 'document_type']);

    $id = $this->postJson(bookingUrl(), bookingPayload())->json('data.id');
    $this->freshRequest()->withToken($this->token)->postJson("/api/v1/booking-requests/{$id}/accept", [])->assertOk();
    expect(tenant()->runAs($this->clinic->id, fn () => Owner::sole()->only(['document_type', 'document_number'])))->toBe(['document_type' => 'CC', 'document_number' => '1098765432']);

    // Same person, new phone: found by document (and warned about the phone); can't be created twice.
    $id = $this->postJson(bookingUrl(), bookingPayload(['starts_at' => '2026-10-05T09:00', 'phone' => '3009998877']))->json('data.id');
    $suggestion = $this->freshRequest()->withToken($this->token)->getJson('/api/v1/booking-requests')->json('data.0.suggestions.0');
    expect($suggestion['match'])->toBe('document')->and($suggestion['mobile_phone'])->toBe('+573186665544');

    $this->freshRequest()->withToken($this->token)->postJson("/api/v1/booking-requests/{$id}/accept", [])->assertJsonValidationErrors('owner_id');
});

it('completes the document of an existing tutor found by phone', function () {
    $owner = makeOwner($this->clinic, ['full_name' => 'Paola Andrea Díaz', 'mobile_phone' => '+573186665544']);
    $id = $this->postJson(bookingUrl(), bookingPayload())->json('data.id');

    expect($this->freshRequest()->withToken($this->token)->getJson('/api/v1/booking-requests')->json('data.0.suggestions.0.match'))->toBe('phone');
    $this->freshRequest()->withToken($this->token)->postJson("/api/v1/booking-requests/{$id}/accept", ['owner_id' => $owner->id])->assertOk();

    expect(tenant()->runAs($this->clinic->id, fn () => $owner->refresh()->document_number))->toBe('1098765432');
});

it('can update the phone of a tutor found by document when accepting', function () {
    $owner = makeOwner($this->clinic, ['full_name' => 'Paola Andrea Díaz', 'mobile_phone' => '+573186665544', 'document_type' => 'CC', 'document_number' => '1098765432']);
    $id = $this->postJson(bookingUrl(), bookingPayload(['phone' => '3205551234']))->json('data.id');

    $this->freshRequest()->withToken($this->token)->postJson("/api/v1/booking-requests/{$id}/accept", ['owner_id' => $owner->id, 'update_phone' => true])->assertOk();

    expect(tenant()->runAs($this->clinic->id, fn () => $owner->refresh()->mobile_phone))->toBe('+573205551234');
});

// ---- B2: WhatsApp to the person who asked -------------------------------

function bookingMessages(string $purpose): Collection
{
    return tenant()->runAs(test()->clinic->id, fn () => Message::where('purpose', $purpose)->orderBy('created_at')->get());
}

function tutorTaps(string $payload): void
{
    Carbon::setTestNow(now()->addSeconds(5)); // frozen clock: keep messages in order
    test()->freshRequest()->withToken(test()->token)
        ->postJson('/api/v1/dev/whatsapp/simulate', ['phone' => '+573186665544', 'payload' => $payload])
        ->assertOk();
}

it('tells the person by WhatsApp that the request was received, with a button to cancel it', function () {
    $this->postJson(bookingUrl(), bookingPayload())->assertCreated();

    $message = bookingMessages('booking_received')->sole();
    expect($message->to_phone)->toBe('+573186665544')
        ->and($message->owner_id)->toBeNull()
        ->and($message->body)->toBe('Hola Paola, recibimos tu solicitud de cita en *'.$this->clinic->name.'* para *Rocky*: vacunación a las 9:30 a. m. del lunes 5 de octubre. La clínica la revisará y te responderá por aquí. *Tu cita aún no está confirmada.*')
        ->and($message->payload['buttons'])->toBe(["cancel:{$message->id}"]);
});

it('lets the person withdraw the request from WhatsApp, freeing the slot and telling the team', function () {
    $id = $this->postJson(bookingUrl(), bookingPayload())->json('data.id');
    $message = bookingMessages('booking_received')->sole();

    tutorTaps("cancel:{$message->id}");
    tutorTaps("cancel_yes:{$message->id}");

    tenant()->runAs($this->clinic->id, function () use ($id) {
        $appointment = Appointment::find($id);
        expect($appointment->status->value)->toBe('cancelled')
            ->and($appointment->cancellation_reason)->toBe('Cancelada por el tutor por WhatsApp')
            ->and(StaffNotification::where('type', 'whatsapp_reply')->where('user_id', $this->admin->id)->value('title'))->toBe('Rocky: el tutor retiró su solicitud en línea');
    });
});

it('tells the person when the clinic accepts (with Reprogramar / Cancelar) or rejects (with the reason and the link)', function () {
    $accepted = $this->postJson(bookingUrl(), bookingPayload(['starts_at' => '2026-10-05T09:00']))->json('data.id');
    $rejected = $this->postJson(bookingUrl(), bookingPayload(['starts_at' => '2026-10-05T09:30', 'phone' => '3001112233', 'document_number' => '52111222', 'name' => 'Jorge Pérez', 'pet_name' => 'Toby']))->json('data.id');

    $this->freshRequest()->withToken($this->token)->postJson("/api/v1/booking-requests/{$accepted}/accept", [])->assertOk();
    $this->freshRequest()->withToken($this->token)->postJson("/api/v1/booking-requests/{$rejected}/reject", ['reason' => 'No tenemos disponibilidad a esa hora.'])->assertOk();

    $ok = bookingMessages('booking_accepted')->sole();
    expect($ok->to_phone)->toBe('+573186665544')
        ->and($ok->owner_id)->not->toBeNull()
        ->and($ok->body)->toContain('aceptó tu solicitud: *Rocky* tiene cita vacunación a las 9:00 a. m. del lunes 5 de octubre.')
        ->and($ok->payload['buttons'])->toBe(["reschedule:{$ok->id}", "cancel:{$ok->id}"]);

    $no = bookingMessages('booking_rejected')->sole();
    expect($no->to_phone)->toBe('+573001112233')
        ->and($no->body)->toContain('Motivo: No tenemos disponibilidad a esa hora. Puedes pedir otra hora aquí: ')
        ->and($no->body)->toEndWith('/reservar/'.$this->clinic->slug);
});

it('gives the clinic a QR of its booking page', function () {
    $this->freshRequest()->withToken($this->token)->getJson('/api/v1/online-booking/settings')
        ->assertJsonPath('data.public_url', rtrim(config('app.frontend_url'), '/').'/reservar/'.$this->clinic->slug);

    $response = $this->freshRequest()->withToken($this->token)->get('/api/v1/online-booking/qr')->assertOk()->assertHeader('Content-Type', 'image/png');
    expect(substr($response->getContent(), 1, 3))->toBe('PNG');
});
