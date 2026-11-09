<?php

use App\Auth\ClinicRole;
use App\Models\PetPublicView;
use Illuminate\Support\Carbon;

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-10-05 10:00', 'America/Bogota'));

    [$this->clinic, $this->admin] = clinicWithMember();
    [, $this->vet] = clinicWithMember(role: ClinicRole::Veterinarian, clinic: $this->clinic);
    $this->clinic->update(['name' => 'Veterinaria Anipet']);
    $this->token = clinicToken($this->admin, $this->clinic);

    $this->owner = makeOwner($this->clinic, [
        'full_name' => 'Luz Marina Pérez', 'mobile_phone' => '+573123334455',
        'document_type' => 'CC', 'document_number' => '63512987', 'address' => 'Cra 27 # 45-12',
    ]);
    $this->pet = makePet($this->owner, ['name' => 'Kira', 'microchip' => '985112003456789']);
});

function vaccinate(string $name, string $occurredAt, ?string $until): void
{
    test()->freshRequest()->withToken(clinicToken(test()->vet, test()->clinic))
        ->postJson('/api/v1/pets/'.test()->pet->id.'/clinical-records', array_filter([
            'type' => 'vaccination',
            'occurred_at' => $occurredAt,
            'next_control_at' => $until,
            'vaccination' => ['vaccine_name' => $name],
        ], fn ($v) => $v !== null))
        ->assertCreated();
}

function publicCard()
{
    return test()->freshRequest()->getJson('/api/v1/public/pets/'.test()->pet->fresh()->public_token);
}

it('shows a minimal public card with the verified vaccination status', function () {
    vaccinate('Rabia', '2025-05-14 10:00', '2026-05-14');      // expired
    vaccinate('Rabia', '2026-05-20 10:00', '2027-05-20');      // latest rabies: valid
    vaccinate('Polivalente', '2026-01-10 10:00', '2027-01-10');
    vaccinate('Bordetella', '2026-03-02 10:00', '2027-03-02');

    $data = publicCard()->assertOk()->json('data');

    expect($data['pet']['name'])->toBe('Kira')
        ->and($data['clinic']['name'])->toBe('Veterinaria Anipet')
        ->and($data['lost'])->toBeNull()
        // Rabies first (what daycares and travel ask for), then the most recent: the card only fits a few.
        ->and($data['vaccinations'])->toBe([
            ['vaccine' => 'Rabia', 'applied_on' => '2026-05-20', 'valid_until' => '2027-05-20', 'status' => 'valid', 'label' => 'Vigente'],
            ['vaccine' => 'Bordetella', 'applied_on' => '2026-03-02', 'valid_until' => '2027-03-02', 'status' => 'valid', 'label' => 'Vigente'],
            ['vaccine' => 'Polivalente', 'applied_on' => '2026-01-10', 'valid_until' => '2027-01-10', 'status' => 'valid', 'label' => 'Vigente'],
        ]);

    // Never documents, addresses, phones (outside lost mode) or the clinical history.
    $json = json_encode($data);
    expect($json)->not->toContain('63512987')->not->toContain('Cra 27')->not->toContain('3123334455')->not->toContain('985112003456789');
});

it('shows the owner contact only in lost mode', function () {
    $this->freshRequest()->withToken($this->token)
        ->postJson("/api/v1/pets/{$this->pet->id}/lost-mode", ['enabled' => true, 'contact_phone' => '+573159998877'])
        ->assertOk();

    expect(publicCard()->json('data.lost'))->toMatchArray(['owner_first_name' => 'Luz', 'contact_phone' => '+573159998877']);

    $this->freshRequest()->withToken($this->token)->postJson("/api/v1/pets/{$this->pet->id}/lost-mode", ['enabled' => false])->assertOk();
    expect(publicCard()->json('data.lost'))->toBeNull();
});

it('hides the page when disabled (unless lost) and invalidates old QR codes', function () {
    $old = $this->pet->public_token;
    tenant()->runAs($this->clinic->id, fn () => $this->pet->update(['public_profile_enabled' => false]));
    publicCard()->assertNotFound();

    $this->freshRequest()->withToken($this->token)->postJson("/api/v1/pets/{$this->pet->id}/lost-mode", ['enabled' => true])->assertOk();
    publicCard()->assertOk(); // lost mode always shows it

    $this->freshRequest()->withToken($this->token)->postJson("/api/v1/pets/{$this->pet->id}/public-token")->assertOk();
    $this->freshRequest()->getJson("/api/v1/public/pets/{$old}")->assertNotFound();
    $this->freshRequest()->getJson('/api/v1/public/pets/'.str_repeat('a', 32))->assertNotFound();
});

it('logs scans once per visitor and mode every 10 minutes, without storing the IP', function () {
    publicCard()->assertOk();
    publicCard()->assertOk();
    Carbon::setTestNow(now()->addMinutes(11));
    publicCard()->assertOk();

    // Turning lost mode on: the next scan counts even from the same visitor, flagged as "lost".
    $this->freshRequest()->withToken($this->token)->postJson("/api/v1/pets/{$this->pet->id}/lost-mode", ['enabled' => true])->assertOk();
    publicCard()->assertOk();

    $views = $this->freshRequest()->withToken($this->token)->getJson("/api/v1/pets/{$this->pet->id}/public-views")->assertOk()->json('data');
    expect($views['total'])->toBe(3)
        ->and($views['views'][0]['in_lost_mode'])->toBeTrue()
        ->and($views['public_url'])->toEndWith('/m/'.$this->pet->fresh()->public_token);

    $stored = tenant()->runAs($this->clinic->id, fn () => PetPublicView::first());
    expect($stored->ip_hash)->toHaveLength(64)->not->toContain('127.0.0.1');
});

it('generates the QR of the card, only for the clinic', function () {
    $qr = $this->freshRequest()->withToken($this->token)->get("/api/v1/pets/{$this->pet->id}/qr")->assertOk()->assertHeader('Content-Type', 'image/png');
    expect(getimagesizefromstring($qr->getContent())[0])->toBeGreaterThan(200);

    [$other, $stranger] = clinicWithMember();
    $this->freshRequest()->withToken(clinicToken($stranger, $other))->get("/api/v1/pets/{$this->pet->id}/qr")->assertNotFound();
});
