<?php

use App\Models\Catalogs\Breed;
use App\Models\Catalogs\ClinicalAlert;
use App\Models\Catalogs\Species;
use App\Models\Pet;
use Illuminate\Support\Carbon;

beforeEach(function () {
    [$this->clinic, $this->admin] = clinicWithMember();
    [$this->otherClinic] = clinicWithMember();
    $this->token = clinicToken($this->admin, $this->clinic);
    $this->owner = makeOwner($this->clinic, ['full_name' => 'Carolina Restrepo']);

    inClinic($this->clinic);
    $this->canine = Species::where('code', 'canine')->first();
    $this->feline = Species::where('code', 'feline')->first();
    $this->labrador = Breed::where('name', 'Labrador Retriever')->first();
    tenant()->forget();
});

function petPayload(array $overrides = []): array
{
    return array_merge([
        'owner_id' => test()->owner->id,
        'name' => 'Luna',
        'species_id' => test()->canine->id,
        'breed_id' => test()->labrador->id,
        'sex' => 'female',
        'birth_date' => '2022-05-14',
        'reproductive_status' => 'neutered',
        'size' => 'large',
        'temperament' => 'docile',
        'color' => 'Dorado',
        'diet' => 'Pro Plan Adulto',
        'weight_kg' => 27.4,
    ], $overrides);
}

function postPet(array $payload)
{
    return test()->freshRequest()->withToken(test()->token)->postJson('/api/v1/pets', $payload);
}

it('registers a pet with Spanish labels, age and a QR token', function () {
    Carbon::setTestNow('2026-09-29 12:00:00');

    $response = postPet(petPayload())
        ->assertCreated()
        ->assertJsonPath('data.species.name', 'Canino')
        ->assertJsonPath('data.breed.name', 'Labrador Retriever')
        ->assertJsonPath('data.sex.label', 'Hembra')
        ->assertJsonPath('data.reproductive_status.label', 'Esterilizado')
        ->assertJsonPath('data.temperament.label', 'Dócil')
        ->assertJsonPath('data.age', '4 años y 4 meses')
        ->assertJsonPath('data.weight_kg', 27.4)
        ->assertJsonPath('data.owner.full_name', 'Carolina Restrepo');

    expect(strlen($response->json('data.public_token')))->toBe(32);
});

it('rejects a breed of another species', function () {
    postPet(petPayload(['species_id' => $this->feline->id]))
        ->assertUnprocessable()
        ->assertJsonPath('errors.breed_id.0', 'La raza no corresponde a la especie.');
});

it('does not accept an owner or a custom breed from another clinic', function () {
    $foreignOwner = makeOwner($this->otherClinic);
    $foreignBreed = tenant()->runAs($this->otherClinic->id, fn () => Breed::create(['species_id' => $this->canine->id, 'name' => 'Raza privada']));

    postPet(petPayload(['owner_id' => $foreignOwner->id]))
        ->assertUnprocessable()
        ->assertJsonPath('errors.owner_id.0', 'El tutor no existe.');

    postPet(petPayload(['breed_id' => $foreignBreed->id]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('breed_id');
});

it('keeps microchips unique per clinic', function () {
    postPet(petPayload(['microchip' => '985112004567890']))->assertCreated();
    postPet(petPayload(['name' => 'Otra', 'microchip' => '985112004567890']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('microchip');
});

it('does not show pets of another clinic', function () {
    $foreignPet = makePet(makeOwner($this->otherClinic));

    $this->freshRequest()->withToken($this->token)->getJson("/api/v1/pets/{$foreignPet->id}")->assertNotFound();
});

it('manages alerts that stay visible on the pet', function () {
    $id = postPet(petPayload())->json('data.id');
    $leish = tenant()->runAs($this->clinic->id, fn () => ClinicalAlert::where('name', 'Leishmaniosis')->first());

    $alertId = $this->freshRequest()->withToken($this->token)
        ->postJson("/api/v1/pets/{$id}/alerts", [
            'type' => 'allergy',
            'description' => 'Alérgica a la penicilina',
            'severity' => 'high',
        ])
        ->assertCreated()
        ->assertJsonPath('data.severity.label', 'Alta')
        ->json('data.id');

    $this->freshRequest()->withToken($this->token)
        ->postJson("/api/v1/pets/{$id}/alerts", ['type' => 'infectious_disease', 'clinical_alert_id' => $leish->id, 'description' => 'Leishmaniosis'])
        ->assertCreated();

    $this->freshRequest()->withToken($this->token)
        ->patchJson("/api/v1/pets/{$id}/alerts/{$alertId}", ['is_active' => false])
        ->assertOk()
        ->assertJsonPath('data.is_active', false);

    $this->freshRequest()->withToken($this->token)->getJson("/api/v1/pets/{$id}")->assertJsonCount(2, 'data.alerts');
});

it('stores lifestyle data once per pet', function () {
    $id = postPet(petPayload())->json('data.id');

    foreach (['Apartamento', 'Casa con patio'] as $housing) {
        $this->freshRequest()->withToken($this->token)
            ->putJson("/api/v1/pets/{$id}/lifestyle", ['housing' => $housing, 'bath_frequency' => 'Cada 15 días'])
            ->assertOk();
    }

    $this->freshRequest()->withToken($this->token)->getJson("/api/v1/pets/{$id}")
        ->assertJsonPath('data.lifestyle.housing', 'Casa con patio');
});

it('regenerates the QR token to invalidate printed cards', function () {
    $response = postPet(petPayload());
    $old = $response->json('data.public_token');

    $new = $this->freshRequest()->withToken($this->token)
        ->postJson("/api/v1/pets/{$response->json('data.id')}/public-token")
        ->assertOk()
        ->json('data.public_token');

    expect($new)->not->toBe($old)->toHaveLength(32);
});

it('records a deceased pet and freezes its age', function () {
    Carbon::setTestNow('2026-09-29 12:00:00');
    $id = postPet(petPayload(['birth_date' => '2015-03-01']))->json('data.id');

    $this->freshRequest()->withToken($this->token)
        ->patchJson("/api/v1/pets/{$id}", ['deceased_at' => '2025-03-10', 'deceased_reason' => 'Insuficiencia renal'])
        ->assertOk()
        ->assertJsonPath('data.age', '10 años');

    expect(tenant()->runAs($this->clinic->id, fn () => Pet::find($id)->isDeceased()))->toBeTrue();
});
