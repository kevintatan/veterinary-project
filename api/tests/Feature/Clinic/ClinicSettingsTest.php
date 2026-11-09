<?php

use App\Auth\ClinicRole;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake(config('filesystems.attachments_disk'));
    [$this->clinic, $this->admin] = clinicWithMember();
    $this->token = clinicToken($this->admin, $this->clinic);
});

it('updates the clinic identity and only the allowed settings', function () {
    $this->freshRequest()->withToken($this->token)->patchJson('/api/v1/clinic', [
        'legal_name' => 'Anipet Veterinaria S.A.S.',
        'tax_id' => '901234567-8',
        'address' => 'Cra 33 # 48-20, Bucaramanga',
        'settings' => ['edit_window_hours' => 48, 'default_appointment_minutes' => 20],
    ])
        ->assertOk()
        ->assertJsonPath('data.tax_id', '901234567-8')
        ->assertJsonPath('data.settings.edit_window_hours', 48)
        ->assertJsonPath('data.settings.prevent_overlaps', true); // untouched default

    expect($this->clinic->fresh()->setting('privacy_policy_version'))->toBe('1'); // merged, not replaced

    $this->freshRequest()->withToken($this->token)
        ->patchJson('/api/v1/clinic', ['settings' => ['edit_window_hours' => 720, 'privacy_policy_version' => '9']])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['settings', 'settings.edit_window_hours']);

    $this->freshRequest()->withToken($this->token)
        ->patchJson('/api/v1/clinic', ['tax_id' => '90.123.456'])
        ->assertJsonValidationErrors('tax_id');
});

it('lets only clinic admins change the clinic', function () {
    [, $vet] = clinicWithMember(role: ClinicRole::Veterinarian, clinic: $this->clinic);
    $vetToken = clinicToken($vet, $this->clinic);

    $this->freshRequest()->withToken($vetToken)->getJson('/api/v1/clinic')->assertOk();
    $this->freshRequest()->withToken($vetToken)->patchJson('/api/v1/clinic', ['name' => 'Otra'])->assertForbidden();
});

it('stores the logo as PNG of at most 600 px and serves it to the clinic', function () {
    $url = $this->freshRequest()->withToken($this->token)
        ->post('/api/v1/clinic/logo', ['logo' => UploadedFile::fake()->image('logo.jpg', 1200, 400)], ['Accept' => 'application/json'])
        ->assertOk()
        ->json('data.logo_url');

    [$w, $h] = getimagesizefromstring(Storage::disk(config('filesystems.attachments_disk'))->get($this->clinic->fresh()->logo_path));
    expect([$w, $h])->toBe([600, 200]);

    $this->freshRequest()->withToken($this->token)->get("/api/v1/{$url}")->assertOk()->assertHeader('Content-Type', 'image/png');
    $this->freshRequest()->withToken($this->token)->getJson('/api/v1/me')->assertJsonPath('data.clinic.logo_url', $url);

    $this->freshRequest()->withToken($this->token)->deleteJson('/api/v1/clinic/logo')->assertJsonPath('data.logo_url', null);
});

it('updates my profile and professional license', function () {
    $this->freshRequest()->withToken($this->token)
        ->patchJson('/api/v1/me', ['name' => 'Carolina Restrepo Gómez', 'professional_license' => 'COMVEZCOL 12345', 'phone' => '+573001112233'])
        ->assertOk()
        ->assertJsonPath('data.user.professional_license', 'COMVEZCOL 12345');
});

it('changes the password, requiring the current one, and closes other sessions', function () {
    $other = clinicToken($this->admin, $this->clinic);

    $this->freshRequest()->withToken($this->token)
        ->putJson('/api/v1/me/password', ['current_password' => 'incorrecta', 'password' => 'NuevaClave2026', 'password_confirmation' => 'NuevaClave2026'])
        ->assertJsonValidationErrors('current_password');

    $this->freshRequest()->withToken($this->token)
        ->putJson('/api/v1/me/password', ['current_password' => 'password', 'password' => 'NuevaClave2026', 'password_confirmation' => 'NuevaClave2026'])
        ->assertNoContent();

    $this->freshRequest()->withToken($this->token)->getJson('/api/v1/me')->assertOk();
    $this->freshRequest()->withToken($other)->getJson('/api/v1/me')->assertUnauthorized();
});
