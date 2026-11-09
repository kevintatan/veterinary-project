<?php

it('returns validation errors in Spanish with readable field names', function () {
    $this->postJson('/api/v1/auth/register', [])
        ->assertUnprocessable()
        ->assertJsonPath('errors.clinic_name.0', 'El campo nombre de la clínica es obligatorio.')
        ->assertJsonPath('errors.email.0', 'El campo correo electrónico es obligatorio.');
});
