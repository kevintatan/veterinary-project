<?php

use App\Auth\ClinicRole;
use App\Models\Catalogs\Breed;
use App\Models\Catalogs\Species;
use App\Tenancy\TenancyViolation;

beforeEach(function () {
    [$this->clinicA, $this->admin] = clinicWithMember();
    [$this->clinicB, $this->adminB] = clinicWithMember();
    $this->token = clinicToken($this->admin, $this->clinicA);
    $this->canine = tenant()->runAs($this->clinicA->id, fn () => Species::where('code', 'canine')->first());
});

it('seeds global catalogs in Spanish', function () {
    $this->freshRequest()->withToken($this->token)->getJson('/api/v1/catalogs/species')
        ->assertOk()
        ->assertJsonPath('data.0.name', 'Canino')
        ->assertJsonPath('data.0.is_global', true);

    $breeds = $this->freshRequest()->withToken($this->token)
        ->getJson("/api/v1/catalogs/breeds?species_id={$this->canine->id}")
        ->assertOk()
        ->json('data.*.name');

    expect($breeds)->toContain('Criollo', 'Labrador Retriever');
});

it('filters vaccines and alerts by species', function () {
    $feline = tenant()->runAs($this->clinicA->id, fn () => Species::where('code', 'feline')->first());

    $vaccines = $this->freshRequest()->withToken($this->token)
        ->getJson("/api/v1/catalogs/vaccines?species_id={$feline->id}")
        ->json('data.*.name');

    expect($vaccines)->toContain('Triple felina (FVRCP)', 'Rabia')
        ->not->toContain('Polivalente canina (DHPPi)');

    // Alerts with no species apply to all.
    $alerts = $this->freshRequest()->withToken($this->token)
        ->getJson("/api/v1/catalogs/clinical-alerts?species_id={$feline->id}")
        ->json('data.*.name');

    expect($alerts)->toContain('Leucemia felina (FeLV)', 'Diabetes mellitus')->not->toContain('Ehrlichiosis');
});

it('lets a clinic add its own entries, invisible to other clinics', function () {
    $this->freshRequest()->withToken($this->token)
        ->postJson('/api/v1/catalogs/breeds', ['species_id' => $this->canine->id, 'name' => 'Fila Colombiano'])
        ->assertCreated()
        ->assertJsonPath('data.is_global', false);

    $inA = $this->freshRequest()->withToken($this->token)->getJson("/api/v1/catalogs/breeds?species_id={$this->canine->id}")->json('data.*.name');
    $inB = $this->freshRequest()->withToken(clinicToken($this->adminB, $this->clinicB))->getJson("/api/v1/catalogs/breeds?species_id={$this->canine->id}")->json('data.*.name');

    expect($inA)->toContain('Fila Colombiano')
        ->and($inB)->not->toContain('Fila Colombiano');
});

it('does not allow editing global entries', function () {
    $global = tenant()->runAs($this->clinicA->id, fn () => Breed::whereNull('clinic_id')->first());

    $this->freshRequest()->withToken($this->token)
        ->patchJson("/api/v1/catalogs/breeds/{$global->id}", ['name' => 'Cambiado'])
        ->assertForbidden();
});

it('protects global rows at the model level too', function () {
    inClinic($this->clinicA);

    Breed::whereNull('clinic_id')->first()->update(['name' => 'Cambiado']);
})->throws(TenancyViolation::class);

it('cannot edit another clinic entry', function () {
    $foreign = tenant()->runAs($this->clinicB->id, fn () => Breed::create(['species_id' => $this->canine->id, 'name' => 'Raza de B']));

    $this->freshRequest()->withToken($this->token)
        ->patchJson("/api/v1/catalogs/breeds/{$foreign->id}", ['name' => 'Hackeada'])
        ->assertNotFound();
});

it('requires catalogs.manage to create entries', function () {
    [, $vet] = clinicWithMember(role: ClinicRole::Veterinarian, clinic: $this->clinicA);

    $this->freshRequest()->withToken(clinicToken($vet, $this->clinicA))
        ->postJson('/api/v1/catalogs/vaccines', ['name' => 'Vacuna X'])
        ->assertForbidden();
});

it('rejects unknown catalogs and malformed filters', function () {
    $this->freshRequest()->withToken($this->token)->getJson('/api/v1/catalogs/users')->assertNotFound();
    $this->freshRequest()->withToken($this->token)->getJson('/api/v1/catalogs/breeds?species_id=abc')->assertUnprocessable();
});
