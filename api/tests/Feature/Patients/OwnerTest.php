<?php

use App\Auth\ClinicRole;
use App\Models\Owner;
use App\Models\OwnerConsent;

beforeEach(function () {
    [$this->clinic, $this->admin] = clinicWithMember();
    [$this->otherClinic, $this->otherAdmin] = clinicWithMember();
    $this->token = clinicToken($this->admin, $this->clinic);
});

function ownerPayload(array $overrides = []): array
{
    return array_replace_recursive([
        'document_type' => 'CC',
        'document_number' => '1.098.745.312',
        'full_name' => 'Carolina Restrepo Gómez',
        'mobile_phone' => '315 748 2096',
        'address' => 'Carrera 33 # 45-12',
        'consents' => ['data_processing' => true, 'whatsapp' => true],
    ], $overrides);
}

function postOwner(array $payload)
{
    return test()->freshRequest()->withToken(test()->token)->postJson('/api/v1/owners', $payload);
}

it('creates an owner with normalized data and Habeas Data evidence', function () {
    $response = postOwner(ownerPayload())
        ->assertCreated()
        ->assertJsonPath('data.mobile_phone', '+573157482096')
        ->assertJsonPath('data.document_number', '1098745312')
        ->assertJsonPath('data.consents.data_processing', true)
        ->assertJsonPath('data.consents.whatsapp', true)
        ->assertJsonPath('data.consents.sms', false);

    $consents = tenant()->runAs($this->clinic->id, fn () => OwnerConsent::where('owner_id', $response->json('data.id'))->get());

    expect($consents)->toHaveCount(2)
        ->and($consents->pluck('type')->sort()->values()->all())->toBe(['channel_whatsapp', 'data_processing'])
        ->and($consents->first()->recorded_by)->toBe($this->admin->id)
        ->and($consents->first()->policy_version)->toBe('1')
        ->and($consents->first()->source)->toBe('in_person');
});

it('requires the data processing authorization', function () {
    postOwner(ownerPayload(['consents' => ['data_processing' => false]]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['consents.data_processing' => 'El tutor debe autorizar el tratamiento de sus datos personales.']);
});

it('does not authorize a channel without the contact data', function () {
    postOwner(ownerPayload(['mobile_phone' => null, 'consents' => ['whatsapp' => true]]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('consents');

    postOwner(ownerPayload(['consents' => ['email' => true]]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('consents');
});

it('rejects an invalid phone with a clear message', function () {
    postOwner(ownerPayload(['mobile_phone' => '12345']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('mobile_phone');
});

it('prevents duplicated documents within a clinic but not across clinics', function () {
    postOwner(ownerPayload())->assertCreated();
    postOwner(ownerPayload(['full_name' => 'Otra persona']))->assertUnprocessable()->assertJsonValidationErrors('document_number');

    $this->freshRequest()->withToken(clinicToken($this->otherAdmin, $this->otherClinic))
        ->postJson('/api/v1/owners', ownerPayload())
        ->assertCreated();
});

it('appends consent changes only when the state changes', function () {
    $id = postOwner(ownerPayload())->json('data.id');

    $this->freshRequest()->withToken($this->token)
        ->putJson("/api/v1/owners/{$id}/consents", ['whatsapp' => false, 'sms' => true, 'data_processing' => true])
        ->assertOk()
        ->assertJsonPath('data.whatsapp', false)
        ->assertJsonPath('data.sms', true)
        ->assertJsonPath('changed', ['channel_whatsapp', 'channel_sms']);

    expect(tenant()->runAs($this->clinic->id, fn () => OwnerConsent::where('owner_id', $id)->count()))->toBe(4);
});

it('keeps consent records immutable', function () {
    $id = postOwner(ownerPayload())->json('data.id');

    inClinic($this->clinic);
    OwnerConsent::where('owner_id', $id)->first()->update(['granted' => false]);
})->throws(LogicException::class);

it('revokes phone channels when the phone is removed', function () {
    $id = postOwner(ownerPayload())->json('data.id');

    $this->freshRequest()->withToken($this->token)
        ->patchJson("/api/v1/owners/{$id}", ['mobile_phone' => null])
        ->assertOk()
        ->assertJsonPath('data.consents.whatsapp', false);
});

it('finds owners by name in any order, without accents and with typos', function (string $q) {
    postOwner(ownerPayload())->assertCreated();
    makeOwner($this->clinic, ['full_name' => 'Andrés Mejía']);

    $this->freshRequest()->withToken($this->token)->getJson('/api/v1/owners?q='.urlencode($q))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.full_name', 'Carolina Restrepo Gómez');
})->with([
    'partial words' => 'caro rest',
    'any order' => 'gomez carolina',
    'accents' => 'GOMEZ',
    'typo' => 'carolna restrepo',
    'document prefix' => '1098745',
    'phone digits' => '7482096',
]);

it('finds owners by their pet name or microchip', function () {
    $owner = makeOwner($this->clinic, ['full_name' => 'Laura Peña']);
    makePet($owner, ['name' => 'Tobías', 'microchip' => '985112004567890']);
    makeOwner($this->clinic, ['full_name' => 'Otro Tutor']);

    $this->freshRequest()->withToken($this->token)->getJson('/api/v1/owners?q=tobias')
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.full_name', 'Laura Peña')
        ->assertJsonPath('data.0.pets.0.name', 'Tobías');

    $this->freshRequest()->withToken($this->token)->getJson('/api/v1/owners?q=985112004567890')
        ->assertJsonCount(1, 'data');
});

it('never returns owners of another clinic, by search or by id', function () {
    $foreign = makeOwner($this->otherClinic, ['full_name' => 'Carolina Ajena']);

    $this->freshRequest()->withToken($this->token)->getJson('/api/v1/owners?q=carolina')->assertJsonCount(0, 'data');
    $this->freshRequest()->withToken($this->token)->getJson("/api/v1/owners/{$foreign->id}")->assertNotFound();
    $this->freshRequest()->withToken($this->token)->patchJson("/api/v1/owners/{$foreign->id}", ['full_name' => 'X'])->assertNotFound();
});

it('does not delete an owner with pets and restricts deletion to admins', function () {
    $owner = makeOwner($this->clinic);
    makePet($owner);
    [, $reception] = clinicWithMember(role: ClinicRole::Receptionist, clinic: $this->clinic);

    $this->freshRequest()->withToken(clinicToken($reception, $this->clinic))
        ->deleteJson("/api/v1/owners/{$owner->id}")
        ->assertForbidden();

    $this->freshRequest()->withToken($this->token)
        ->deleteJson("/api/v1/owners/{$owner->id}")
        ->assertUnprocessable();

    $empty = makeOwner($this->clinic);
    $this->freshRequest()->withToken($this->token)->deleteJson("/api/v1/owners/{$empty->id}")->assertNoContent();

    expect(tenant()->runAs($this->clinic->id, fn () => Owner::withTrashed()->find($empty->id)->trashed()))->toBeTrue();
});
