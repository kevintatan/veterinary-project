<?php

use App\Auth\ClinicRole;
use App\Models\Activity;

beforeEach(function () {
    [$this->clinic, $this->admin] = clinicWithMember();
    [, $this->vet] = clinicWithMember(role: ClinicRole::Veterinarian, clinic: $this->clinic);
    $this->vetToken = clinicToken($this->vet, $this->clinic);
    $this->pet = makePet(makeOwner($this->clinic), ['name' => 'Luna', 'weight_kg' => 25]);
});

function exportedRecords(): ?int
{
    return Activity::query()->where('event', 'exported')->latest('id')->first()?->properties['records'];
}

it('exports the whole clinical history as a PDF and logs who exported it', function () {
    createRecord(consultationPayload())->assertCreated();
    createRecord(['type' => 'note', 'note' => ['body' => 'Tutora llama: mejoró.']])->assertCreated();

    $response = $this->freshRequest()->withToken($this->vetToken)
        ->get("/api/v1/pets/{$this->pet->id}/clinical-history/pdf")
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');

    expect($response->getContent())->toStartWith('%PDF')
        ->and($response->headers->get('Content-Disposition'))->toContain('historia-clinica-luna-');

    $log = Activity::query()->where('event', 'exported')->sole();
    expect($log->causer_id)->toBe($this->vet->id)
        ->and($log->subject_id)->toBe($this->pet->id)
        ->and($log->clinic_id)->toBe($this->clinic->id)
        ->and($log->properties['records'])->toBe(2);
});

it('filters the export by period and type', function () {
    createRecord(consultationPayload(['occurred_at' => '2026-09-01 10:00']))->assertCreated();
    createRecord(consultationPayload(['occurred_at' => '2026-09-20 10:00']))->assertCreated();
    createRecord(['type' => 'note', 'note' => ['body' => 'Nota'], 'occurred_at' => '2026-09-21 10:00'])->assertCreated();

    $this->freshRequest()->withToken($this->vetToken)
        ->get("/api/v1/pets/{$this->pet->id}/clinical-history/pdf?from=2026-09-15&to=2026-09-30&types[]=consultation")
        ->assertOk();

    expect(exportedRecords())->toBe(1);
});

it('exports a single record, e.g. a prescription to print', function () {
    $id = createRecord([
        'type' => 'prescription',
        'prescription' => ['items' => [['medication' => 'Amoxicilina', 'dose' => '1 tab', 'frequency' => 'Cada 12 h']]],
    ])->json('data.id');

    $response = $this->freshRequest()->withToken($this->vetToken)->get("/api/v1/clinical-records/{$id}/pdf")->assertOk();

    expect($response->headers->get('Content-Disposition'))->toContain('formula-medica-luna-')
        ->and(exportedRecords())->toBe(1);
});

it('does not export another clinic’s patient', function () {
    createRecord(consultationPayload())->assertCreated();
    [$other, $stranger] = clinicWithMember();

    $this->freshRequest()->withToken(clinicToken($stranger, $other))
        ->get("/api/v1/pets/{$this->pet->id}/clinical-history/pdf")
        ->assertNotFound();

    expect(Activity::query()->where('event', 'exported')->count())->toBe(0);
});
