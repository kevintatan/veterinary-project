<?php

use App\Auth\ClinicRole;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Catalogs\Vaccine;
use App\Models\Clinical\ClinicalRecord;
use App\Models\Pet;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-10-05 10:00', 'America/Bogota'));

    [$this->clinic, $this->admin] = clinicWithMember();
    [, $this->vet] = clinicWithMember(role: ClinicRole::Veterinarian, clinic: $this->clinic);
    [, $this->vet2] = clinicWithMember(role: ClinicRole::Veterinarian, clinic: $this->clinic);
    $this->vetToken = clinicToken($this->vet, $this->clinic);
    $this->pet = makePet(makeOwner($this->clinic), ['name' => 'Luna', 'weight_kg' => 25]);
});

function consultationPayload(array $overrides = []): array
{
    return array_replace_recursive([
        'type' => 'consultation',
        'consultation' => [
            'reason' => 'Se rasca las orejas',
            'subjective' => 'Desde hace una semana sacude la cabeza.',
            'objective' => 'Conducto auditivo eritematoso, cerumen oscuro.',
            'assessment' => 'Otitis externa bilateral.',
            'plan_treatment' => 'Limpieza ótica y gotas por 10 días.',
        ],
        'vital_signs' => ['weight_kg' => 27.4, 'temperature_c' => 38.6, 'heart_rate' => 96, 'mucous_membranes' => 'pink'],
        'findings' => [
            ['system' => 'ears', 'status' => 'abnormal', 'notes' => 'Eritema bilateral'],
            ['system' => 'cardiovascular', 'status' => 'normal'],
            ['system' => 'nervous', 'status' => 'not_evaluated'],
        ],
        'diagnoses' => [
            ['kind' => 'presumptive', 'description' => 'Otitis por Malassezia'],
            ['kind' => 'definitive', 'description' => 'Otitis externa bilateral'],
        ],
        'next_control_at' => '2026-10-15',
    ], $overrides);
}

function createRecord(array $payload, ?string $token = null, ?Pet $pet = null)
{
    $pet ??= test()->pet;

    return test()->freshRequest()->withToken($token ?? test()->vetToken)
        ->postJson("/api/v1/pets/{$pet->id}/clinical-records", $payload);
}

it('records a SOIP consultation with vital signs, exam findings and diagnoses', function () {
    createRecord(consultationPayload())
        ->assertCreated()
        ->assertJsonPath('data.type.label', 'Consulta')
        ->assertJsonPath('data.summary', 'Otitis externa bilateral')
        ->assertJsonPath('data.author.name', $this->vet->name)
        ->assertJsonPath('data.occurred_at', '2026-10-05T10:00:00-05:00')
        ->assertJsonPath('data.next_control_at', '2026-10-15')
        ->assertJsonPath('data.visible_to_owner', false)
        ->assertJsonPath('data.can_edit', true)
        ->assertJsonPath('data.detail.consultation.assessment', 'Otitis externa bilateral.')
        ->assertJsonPath('data.detail.vital_signs.temperature_c', 38.6)
        ->assertJsonPath('data.detail.vital_signs.mucous_membranes.label', 'Rosadas')
        ->assertJsonCount(2, 'data.detail.findings') // "not evaluated" is not stored
        ->assertJsonPath('data.detail.findings.0.system.label', 'Oídos')
        ->assertJsonPath('data.detail.diagnoses.1.kind.label', 'Definitivo');

    expect((float) tenant()->runAs($this->clinic->id, fn () => $this->pet->fresh()->weight_kg))->toBe(27.4);
});

it('does not overwrite the current weight with an older record', function () {
    createRecord(consultationPayload())->assertCreated();
    createRecord(consultationPayload(['occurred_at' => '2026-09-01T09:00', 'vital_signs' => ['weight_kg' => 22.0]]))->assertCreated();

    expect((float) tenant()->runAs($this->clinic->id, fn () => $this->pet->fresh()->weight_kg))->toBe(27.4);
});

it('records a vaccination with brand and lot, suggesting the next dose from the catalog', function () {
    $rabies = tenant()->runAs($this->clinic->id, fn () => Vaccine::where('name', 'Rabia')->first());

    createRecord([
        'type' => 'vaccination',
        'vaccination' => ['vaccine_id' => $rabies->id, 'manufacturer' => 'Nobivac', 'lot_number' => 'A123B', 'dose_number' => 1],
    ])
        ->assertCreated()
        ->assertJsonPath('data.summary', 'Rabia · Nobivac · lote A123B')
        ->assertJsonPath('data.detail.vaccination.vaccine_name', 'Rabia')
        ->assertJsonPath('data.next_control_at', '2027-10-05')
        ->assertJsonPath('data.visible_to_owner', true); // vaccines are shown to the owner by default
});

it('records a prescription and requires at least one medication', function () {
    createRecord(['type' => 'prescription', 'prescription' => ['diagnosis' => 'Otitis', 'items' => []]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('prescription.items');

    createRecord(['type' => 'prescription', 'prescription' => ['diagnosis' => 'Otitis', 'items' => [
        ['medication' => 'Otomax', 'dose' => '5 gotas', 'frequency' => 'Cada 12 horas', 'duration' => '10 días'],
        ['medication' => 'Meloxicam 1.5 mg/ml', 'dose' => '0.5 ml', 'frequency' => 'Cada 24 horas'],
    ]]])
        ->assertCreated()
        ->assertJsonPath('data.summary', 'Otomax, Meloxicam 1.5 mg/ml')
        ->assertJsonCount(2, 'data.detail.prescription.items');
});

it('links a prescription to its consultation', function () {
    $consultation = createRecord(consultationPayload())->json('data.id');

    createRecord(['type' => 'note', 'note' => ['body' => 'Tutor informa mejoría'], 'parent_record_id' => $consultation])
        ->assertCreated()
        ->assertJsonPath('data.parent_record_id', $consultation);
});

it('shows the pet timeline newest first with counts per type', function () {
    createRecord(['type' => 'note', 'note' => ['body' => 'Primera'], 'occurred_at' => '2026-10-01T09:00'])->assertCreated();
    createRecord(consultationPayload())->assertCreated();
    createRecord(['type' => 'deworming', 'deworming' => ['kind' => 'internal', 'product_name' => 'Drontal Plus']])->assertCreated();

    $response = $this->freshRequest()->withToken($this->vetToken)->getJson("/api/v1/pets/{$this->pet->id}/clinical-records")
        ->assertOk()
        ->assertJsonCount(3, 'data')
        ->assertJsonPath('data.2.summary', 'Primera')
        ->assertJsonPath('meta.counts.consultation.total', 1)
        ->assertJsonPath('meta.counts.deworming.total', 1);

    $this->freshRequest()->withToken($this->vetToken)->getJson("/api/v1/pets/{$this->pet->id}/clinical-records?type=deworming")
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.summary', 'Interna · Drontal Plus');
});

it('lets only the author edit within the window', function () {
    $id = createRecord(consultationPayload())->json('data.id');

    $this->freshRequest()->withToken($this->vetToken)
        ->patchJson("/api/v1/clinical-records/{$id}", ['consultation' => ['reason' => 'Otitis', 'assessment' => 'Otitis externa']])
        ->assertOk()
        ->assertJsonPath('data.detail.consultation.assessment', 'Otitis externa');

    $this->freshRequest()->withToken(clinicToken($this->vet2, $this->clinic))
        ->patchJson("/api/v1/clinical-records/{$id}", ['next_control_note' => 'x'])
        ->assertForbidden()
        ->assertJsonPath('message', 'Solo el autor del registro o un administrador puede modificarlo.');
});

it('locks records after 24 hours; only an admin can edit them then', function () {
    $created = createRecord(consultationPayload());
    $id = $created->json('data.id');

    // The UI shows "editable until …": 24 h after creation, in clinic time.
    expect(Carbon::parse($created->json('data.locks_at'))->diffInMinutes(Carbon::parse($created->json('data.created_at'))))->toEqual(-24 * 60.0);

    Carbon::setTestNow(now()->addHours(25));

    $this->freshRequest()->withToken($this->vetToken)
        ->patchJson("/api/v1/clinical-records/{$id}", ['next_control_note' => 'Cambio tardío'])
        ->assertForbidden()
        ->assertJsonPath('message', 'El registro está cerrado (pasó la ventana de edición). Solo un administrador puede modificarlo.');

    $this->freshRequest()->withToken(clinicToken($this->admin, $this->clinic))
        ->patchJson("/api/v1/clinical-records/{$id}", ['next_control_note' => 'Corrección del admin'])
        ->assertOk()
        ->assertJsonPath('data.is_locked', true)
        ->assertJsonPath('data.locks_at', null);
});

it('persists the lock with the scheduled command', function () {
    $old = createRecord(consultationPayload())->json('data.id');
    Carbon::setTestNow(now()->addHours(25));
    $recent = createRecord(consultationPayload())->json('data.id');

    $this->artisan('clinical-records:lock')->assertSuccessful();

    inClinic($this->clinic);
    expect(ClinicalRecord::find($old)->locked_at)->not->toBeNull()
        ->and(ClinicalRecord::find($recent)->locked_at)->toBeNull();
});

it('voids instead of deleting, keeping the record visible', function () {
    $id = createRecord(consultationPayload())->json('data.id');

    $this->freshRequest()->withToken($this->vetToken)->postJson("/api/v1/clinical-records/{$id}/void", [])->assertUnprocessable();

    $this->freshRequest()->withToken($this->vetToken)
        ->postJson("/api/v1/clinical-records/{$id}/void", ['reason' => 'Registrado en la mascota equivocada'])
        ->assertOk()
        ->assertJsonPath('data.is_voided', true)
        ->assertJsonPath('data.void.by', $this->vet->name)
        ->assertJsonPath('data.can_edit', false);

    $this->freshRequest()->withToken($this->vetToken)
        ->patchJson("/api/v1/clinical-records/{$id}", ['next_control_note' => 'x'])
        ->assertForbidden();

    $this->freshRequest()->withToken($this->vetToken)->getJson("/api/v1/pets/{$this->pet->id}/clinical-records")
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('meta.counts.consultation.total', 0);

    $this->freshRequest()->withToken($this->vetToken)->getJson("/api/v1/pets/{$this->pet->id}/clinical-records?include_voided=0")
        ->assertJsonCount(0, 'data');
});

it('never deletes clinical records, even from code', function () {
    $id = createRecord(consultationPayload())->json('data.id');

    inClinic($this->clinic);
    ClinicalRecord::find($id)->delete();
})->throws(LogicException::class);

it('lets reception read but not write clinical records', function () {
    [, $reception] = clinicWithMember(role: ClinicRole::Receptionist, clinic: $this->clinic);
    $token = clinicToken($reception, $this->clinic);
    createRecord(consultationPayload())->assertCreated();

    $this->freshRequest()->withToken($token)->getJson("/api/v1/pets/{$this->pet->id}/clinical-records")->assertOk();
    createRecord(consultationPayload(), $token)->assertForbidden();
});

it('rejects future dates and appointments of another pet', function () {
    createRecord(consultationPayload(['occurred_at' => '2026-10-05T18:00']))
        ->assertUnprocessable()
        ->assertJsonPath('errors.occurred_at.0', 'La fecha de atención no puede ser futura.');

    $otherPet = makePet(makeOwner($this->clinic));
    $appointmentId = tenant()->runAs($this->clinic->id, fn () => Appointment::create([
        'branch_id' => Branch::first()->id,
        'pet_id' => $otherPet->id,
        'owner_id' => $otherPet->owner_id,
        'starts_at' => now(),
        'ends_at' => now()->addMinutes(30),
    ])->id);

    createRecord(consultationPayload(['appointment_id' => $appointmentId]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('appointment_id');
});

it('isolates clinical records between clinics', function () {
    $id = createRecord(consultationPayload())->json('data.id');
    [$other, $otherAdmin] = clinicWithMember();
    $otherToken = clinicToken($otherAdmin, $other);

    $this->freshRequest()->withToken($otherToken)->getJson("/api/v1/clinical-records/{$id}")->assertNotFound();
    $this->freshRequest()->withToken($otherToken)->getJson("/api/v1/pets/{$this->pet->id}/clinical-records")->assertNotFound();
    createRecord(consultationPayload(), $otherToken)->assertNotFound();
});

it('stores attachments privately, per clinic, and serves them only to that clinic', function () {
    Storage::fake('local');
    $id = createRecord(consultationPayload())->json('data.id');

    $attachmentId = $this->freshRequest()->withToken($this->vetToken)
        ->post("/api/v1/clinical-records/{$id}/attachments", [
            'file' => UploadedFile::fake()->create('hemograma.pdf', 120, 'application/pdf'),
            'category' => 'lab_result',
        ], ['Accept' => 'application/json'])
        ->assertCreated()
        ->assertJsonPath('data.category', 'lab_result')
        ->json('data.id');

    $stored = Storage::disk('local')->allFiles("clinics/{$this->clinic->id}");
    expect($stored)->toHaveCount(1);

    $this->freshRequest()->withToken($this->vetToken)->get("/api/v1/attachments/{$attachmentId}/download")
        ->assertOk()
        ->assertDownload('hemograma.pdf');

    [$other, $otherAdmin] = clinicWithMember();
    $this->freshRequest()->withToken(clinicToken($otherAdmin, $other))
        ->getJson("/api/v1/attachments/{$attachmentId}/download")
        ->assertNotFound();

    $this->freshRequest()->withToken($this->vetToken)->getJson("/api/v1/clinical-records/{$id}")
        ->assertJsonCount(1, 'data.attachments');
});

it('rejects dangerous file types', function () {
    Storage::fake('local');
    $id = createRecord(consultationPayload())->json('data.id');

    $this->freshRequest()->withToken($this->vetToken)
        ->post("/api/v1/clinical-records/{$id}/attachments", [
            'file' => UploadedFile::fake()->create('script.php', 1, 'application/x-php'),
        ], ['Accept' => 'application/json'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('file');
});
