<?php

use App\Auth\ClinicRole;

beforeEach(function () {
    [$this->clinic, $this->admin] = clinicWithMember();
    [, $this->reception] = clinicWithMember(role: ClinicRole::Receptionist, clinic: $this->clinic);
    $this->token = clinicToken($this->admin, $this->clinic);
    $this->pet = makePet(makeOwner($this->clinic, ['full_name' => 'Luz Marina Pérez', 'mobile_phone' => '+573123334455']), ['name' => 'Kira']);
});

function cardData(string $token)
{
    return test()->freshRequest()->withToken($token)->getJson('/api/v1/pets/'.test()->pet->id.'/card')->assertOk()->json('data');
}

it('gives the designer the pet, owner, clinic and the resolved design', function () {
    $data = cardData($this->token);

    expect($data['pet']['name'])->toBe('Kira')
        ->and($data['pet']['code'])->toMatch('/^[0-9A-F]{4}-[0-9A-F]{4}$/')
        ->and($data['owner']['full_name'])->toBe('Luz Marina Pérez')
        ->and($data['design'])->toBe(['template' => 'classic', 'color' => '#166534', 'show_photo' => true, 'show_owner_phone' => false, 'show_vaccines' => true])
        ->and($data['has_own_design'])->toBeFalse();
});

it('resolves pet design over the clinic default over Zooetia default', function () {
    $this->freshRequest()->withToken($this->token)
        ->putJson('/api/v1/clinic/pet-card', ['template' => 'modern', 'color' => '#2563EB', 'show_photo' => true, 'show_owner_phone' => false, 'show_vaccines' => true])
        ->assertOk();
    expect(cardData($this->token)['design']['template'])->toBe('modern');

    // Reception can design a pet's card (patients.manage) but not the clinic default.
    $receptionToken = clinicToken($this->reception, $this->clinic);
    $this->freshRequest()->withToken($receptionToken)
        ->putJson("/api/v1/pets/{$this->pet->id}/card-design", ['design' => ['template' => 'paws', 'color' => '#DB2777', 'show_photo' => false, 'show_owner_phone' => true, 'show_vaccines' => false, 'extra' => 'x']])
        ->assertOk()
        ->assertJsonPath('data.design.template', 'paws')
        ->assertJsonPath('data.has_own_design', true);
    expect($this->pet->fresh()->card_design)->not->toHaveKey('extra');

    $this->freshRequest()->withToken($receptionToken)
        ->putJson('/api/v1/clinic/pet-card', ['template' => 'classic', 'color' => '#000000', 'show_photo' => true, 'show_owner_phone' => false, 'show_vaccines' => true])
        ->assertForbidden();

    // Back to the clinic default.
    $this->freshRequest()->withToken($this->token)->putJson("/api/v1/pets/{$this->pet->id}/card-design", ['design' => null])
        ->assertJsonPath('data.design.template', 'modern')
        ->assertJsonPath('data.has_own_design', false);
});

it('validates the design', function () {
    $this->freshRequest()->withToken($this->token)
        ->putJson("/api/v1/pets/{$this->pet->id}/card-design", ['design' => ['template' => 'neon', 'color' => 'red', 'show_photo' => true, 'show_owner_phone' => false, 'show_vaccines' => true]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['design.template', 'design.color']);
});
