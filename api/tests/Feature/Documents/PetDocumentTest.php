<?php

use App\Auth\ClinicRole;
use App\Models\Documents\PetDocument;
use App\Models\TutorAccount;
use App\Models\TutorAccountOwner;
use App\Notifications\DocumentSignRequestNotification;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake(config('filesystems.attachments_disk'));

    [$this->clinic, $this->admin] = clinicWithMember();
    [, $this->vet] = clinicWithMember(role: ClinicRole::Veterinarian, clinic: $this->clinic);
    $this->clinic->update(['name' => 'Veterinaria Anipet', 'tax_id' => '900123456-7']);
    $this->vet->update(['name' => 'Dra. Carolina Restrepo', 'professional_license' => 'MV-12345']);
    $this->vetToken = clinicToken($this->vet, $this->clinic);

    $this->owner = makeOwner($this->clinic, ['full_name' => 'Andrés Felipe Mejía', 'document_type' => 'CC', 'document_number' => '91345678', 'mobile_phone' => '+573004561234']);
    $this->pet = makePet($this->owner, ['name' => 'Michi', 'sex' => 'female', 'weight_kg' => 4.1, 'birth_date' => '2021-03-20']);
});

/** A signature as the pad sends it: strokes on a transparent PNG, as a data URL. */
function strokes(bool $empty = false): string
{
    $img = imagecreatetruecolor(600, 200);
    imagesavealpha($img, true);
    imagefill($img, 0, 0, imagecolorallocatealpha($img, 0, 0, 0, 127));
    if (! $empty) {
        $ink = imagecolorallocate($img, 16, 24, 40);
        imagesetthickness($img, 4);
        imageline($img, 40, 150, 200, 40, $ink);
        imageline($img, 200, 40, 320, 160, $ink);
        imageline($img, 320, 160, 560, 60, $ink);
    }
    ob_start();
    imagepng($img);

    return 'data:image/png;base64,'.base64_encode((string) ob_get_clean());
}

function euthanasia(array $overrides = []): array
{
    return test()->freshRequest()->withToken(test()->vetToken)->postJson('/api/v1/pets/'.test()->pet->id.'/documents', array_replace_recursive([
        'type' => 'euthanasia',
        'data' => ['diagnosis' => 'Insuficiencia renal crónica terminal.', 'reason' => 'Dolor no controlable.', 'disposition' => 'cremation_individual', 'owner_present' => true, 'cost' => 350000],
    ], $overrides))->assertCreated()->json('data');
}

function signAs(string $docId, string $role, ?string $token = null, array $extra = [])
{
    return test()->freshRequest()->withToken($token ?? test()->vetToken)
        ->postJson("/api/v1/documents/{$docId}/signatures", ['role' => $role, 'image' => strokes(), 'accepted' => true, ...$extra]);
}

it('fills the template with the clinic, owner, pet, vet and case data', function () {
    $types = $this->freshRequest()->withToken($this->vetToken)->getJson('/api/v1/document-types')->assertOk()->json('data');
    expect(collect($types)->pluck('code')->all())->toBe(['euthanasia', 'surgery', 'hospitalization', 'voluntary_discharge']);

    $doc = euthanasia();
    $text = collect($doc['snapshot']['blocks'])->map(fn ($b) => $b['text'] ?? implode(' ', $b['items']))->implode("\n");

    expect($doc['status']['value'])->toBe('draft')
        ->and($doc['title'])->toBe('Consentimiento informado para eutanasia')
        ->and($text)->toContain('Yo, Andrés Felipe Mejía, identificado(a) con Cédula de ciudadanía 91345678')
        ->and($text)->toContain('Michi (canino, raza no registrada, hembra')
        ->and($text)->toContain('4,1 kg')
        ->and($text)->toContain('Insuficiencia renal crónica terminal.')
        ->and($text)->toContain('Cremación individual, con entrega de las cenizas al tutor')
        ->and($text)->toContain('$ 350.000')
        ->and($text)->toContain('Deseo estar presente')
        ->and($doc['snapshot']['vet'])->toMatchArray(['name' => 'Dra. Carolina Restrepo', 'license' => 'MV-12345'])
        ->and(collect($doc['snapshot']['signers'])->keys()->all())->toBe(['owner', 'vet']); // no witness unless asked
});

it('validates the case fields and requires the owner document and the vet license', function () {
    $this->freshRequest()->withToken($this->vetToken)->postJson("/api/v1/pets/{$this->pet->id}/documents", ['type' => 'euthanasia', 'data' => ['disposition' => 'x']])
        ->assertJsonValidationErrors(['data.diagnosis', 'data.reason', 'data.disposition']);

    tenant()->runAs($this->clinic->id, fn () => $this->owner->update(['document_number' => null]));
    $this->freshRequest()->withToken($this->vetToken)->postJson("/api/v1/pets/{$this->pet->id}/documents", ['type' => 'hospitalization', 'data' => ['reason' => 'Vómito']])
        ->assertJsonValidationErrors('owner');

    tenant()->runAs($this->clinic->id, fn () => $this->owner->update(['document_number' => '91345678']));
    $this->vet->update(['professional_license' => null]);
    $this->freshRequest()->withToken($this->vetToken)->postJson("/api/v1/pets/{$this->pet->id}/documents", ['type' => 'hospitalization', 'data' => ['reason' => 'Vómito']])
        ->assertJsonValidationErrors('vet_id');
});

it('locks the content at the first signature and stores the signed PDF when everyone signed', function () {
    $doc = euthanasia();

    $signing = signAs($doc['id'], 'owner')->assertOk()->json('data');
    expect($signing['status']['value'])->toBe('signing')
        ->and($signing['content_hash'])->toHaveLength(64)
        ->and($signing['pending'])->toBe(['vet']);

    // Nothing can change once someone signed.
    $this->freshRequest()->withToken($this->vetToken)->patchJson("/api/v1/documents/{$doc['id']}", ['data' => ['diagnosis' => 'Otro', 'reason' => 'Otro', 'disposition' => 'owner_takes']])
        ->assertJsonValidationErrors('type');
    signAs($doc['id'], 'owner')->assertJsonValidationErrors('role'); // once per role

    // The vet signs from their own session only.
    signAs($doc['id'], 'vet', clinicToken($this->admin, $this->clinic))->assertJsonValidationErrors('role');
    $signed = signAs($doc['id'], 'vet')->assertOk()->json('data');

    expect($signed['status']['value'])->toBe('signed')
        ->and($signed['signed_at'])->not->toBeNull()
        ->and(collect($signed['signature_list'])->pluck('name')->all())->toBe(['Andrés Felipe Mejía', 'Dra. Carolina Restrepo']);

    [$stored, $hashes] = tenant()->runAs($this->clinic->id, function () use ($doc) {
        $d = PetDocument::find($doc['id']);

        return [$d, $d->signatures()->pluck('content_hash')->unique()->values()->all()];
    });
    $bytes = Storage::disk(config('filesystems.attachments_disk'))->get($stored->pdf_path);
    expect(hash('sha256', $bytes))->toBe($stored->pdf_sha256)
        ->and($hashes)->toBe([$stored->content_hash]); // everyone signed the same content

    // The PDF served is the stored original, byte for byte.
    $pdf = $this->freshRequest()->withToken($this->vetToken)->get("/api/v1/documents/{$doc['id']}/pdf")->assertOk();
    expect($pdf->streamedContent())->toBe($bytes);

    signAs($doc['id'], 'owner')->assertJsonValidationErrors('role'); // already signed
});

it('asks for a witness only when the vet wants one', function () {
    $doc = euthanasia(['with_witness' => true]);
    expect($doc['snapshot']['signers']['witness']['required'])->toBeTrue();

    signAs($doc['id'], 'owner')->assertOk();
    expect(signAs($doc['id'], 'vet')->json('data.status.value'))->toBe('signing'); // still missing the witness

    signAs($doc['id'], 'witness')->assertJsonValidationErrors('signer_name');
    $done = signAs($doc['id'], 'witness', null, ['signer_name' => 'Laura Gómez', 'signer_document' => 'CC 1098765432'])->assertOk()->json('data');
    expect($done['status']['value'])->toBe('signed');
});

it('rejects an empty pad and a signature without reading and accepting', function () {
    $doc = euthanasia();

    $this->freshRequest()->withToken($this->vetToken)->postJson("/api/v1/documents/{$doc['id']}/signatures", ['role' => 'owner', 'image' => strokes(empty: true), 'accepted' => true])
        ->assertJsonValidationErrors('image');
    $this->freshRequest()->withToken($this->vetToken)->postJson("/api/v1/documents/{$doc['id']}/signatures", ['role' => 'owner', 'image' => strokes()])
        ->assertJsonValidationErrors('accepted');
    $this->freshRequest()->withToken($this->vetToken)->postJson("/api/v1/documents/{$doc['id']}/signatures", ['role' => 'owner', 'image' => 'data:text/html;base64,PHA+', 'accepted' => true])
        ->assertJsonValidationErrors('image');
});

it('voids with a reason, never deletes, and keeps documents inside their clinic', function () {
    $doc = euthanasia();
    signAs($doc['id'], 'owner');
    signAs($doc['id'], 'vet');

    $this->freshRequest()->withToken($this->vetToken)->postJson("/api/v1/documents/{$doc['id']}/void", ['reason' => 'x'])->assertJsonValidationErrors('reason');
    $voided = $this->freshRequest()->withToken($this->vetToken)->postJson("/api/v1/documents/{$doc['id']}/void", ['reason' => 'Se firmó con la mascota equivocada'])->assertOk()->json('data');
    expect($voided['status']['value'])->toBe('voided')->and($voided['void_reason'])->toBe('Se firmó con la mascota equivocada');

    $list = $this->freshRequest()->withToken($this->vetToken)->getJson("/api/v1/pets/{$this->pet->id}/documents")->assertOk()->json('data');
    expect($list)->toHaveCount(1);

    [$other, $stranger] = clinicWithMember();
    $this->freshRequest()->withToken(clinicToken($stranger, $other))->getJson("/api/v1/documents/{$doc['id']}")->assertNotFound();
    $this->freshRequest()->withToken(clinicToken($stranger, $other))->get("/api/v1/documents/{$doc['id']}/pdf")->assertNotFound();
});

it('lets reception see documents but not create or sign them', function () {
    [, $reception] = clinicWithMember(role: ClinicRole::Receptionist, clinic: $this->clinic);
    $token = clinicToken($reception, $this->clinic);
    $doc = euthanasia();

    $this->freshRequest()->withToken($token)->getJson("/api/v1/documents/{$doc['id']}")->assertOk();
    $this->freshRequest()->withToken($token)->postJson("/api/v1/pets/{$this->pet->id}/documents", ['type' => 'hospitalization', 'data' => ['reason' => 'x']])->assertForbidden();
    signAs($doc['id'], 'owner', $token)->assertForbidden();
});

// ------------------------------------------------------------------ D4: the clinic's own wording

function adminToken(): string
{
    return clinicToken(test()->admin, test()->clinic);
}

it('lets the clinic reword a document: new documents use it, existing ones keep their text', function () {
    $before = euthanasia();

    $templates = $this->freshRequest()->withToken(adminToken())->getJson('/api/v1/document-templates')->assertOk()->json('data');
    expect(collect($templates['types'])->firstWhere('code', 'euthanasia')['customized'])->toBeFalse()
        ->and(collect($templates['placeholders'])->pluck('key'))->toContain('tutor', 'mascota', 'veterinario_tp');

    $body = [
        ['text' => 'Yo, {{tutor}}, con {{tutor_doc}}, responsable de {{mascota}}, autorizo a {{clinica}} a practicar la eutanasia humanitaria.'],
        ['list' => ['Diagnóstico: {{campo.diagnosis}}', 'Motivo: {{campo.reason}}']],
        ['text' => 'Valor: {{campo.cost}}.', 'when' => 'cost'],
    ];
    $saved = $this->freshRequest()->withToken(adminToken())->putJson('/api/v1/document-templates/euthanasia', ['body' => $body])->assertOk()->json('data');
    $euth = collect($saved['types'])->firstWhere('code', 'euthanasia');
    expect($euth['customized'])->toBeTrue()->and($euth['version'])->toBe(1)->and($euth['updated_by'])->toBe($this->admin->name);

    $after = euthanasia();
    expect($after['snapshot']['blocks'][0]['text'])->toBe('Yo, Andrés Felipe Mejía, con Cédula de ciudadanía 91345678, responsable de Michi, autorizo a Veterinaria Anipet a practicar la eutanasia humanitaria.')
        ->and($after['snapshot']['blocks'])->toHaveCount(3)
        ->and($after['snapshot']['template'])->toBe(['base_version' => 1, 'clinic_version' => 1]);

    // The document created before keeps Zooetia's text.
    $old = $this->freshRequest()->withToken($this->vetToken)->getJson("/api/v1/documents/{$before['id']}")->json('data');
    expect($old['snapshot']['blocks'][0]['text'])->toStartWith('Yo, Andrés Felipe Mejía, identificado(a) con');

    // Restore Zooetia's text.
    $this->freshRequest()->withToken(adminToken())->deleteJson('/api/v1/document-templates/euthanasia')->assertOk();
    expect(euthanasia()['snapshot']['blocks'][0]['text'])->toStartWith('Yo, Andrés Felipe Mejía, identificado(a) con');
});

it('rejects unknown data, empty paragraphs and texts that no longer identify who authorizes', function () {
    $put = fn (array $body) => $this->freshRequest()->withToken(adminToken())->putJson('/api/v1/document-templates/surgery', ['body' => $body]);

    $put([['text' => 'Yo, {{tutor}} ({{tutor_doc}}), autorizo la cirugía de {{mascot}} y {{campo.inventado}}.']])
        ->assertJsonValidationErrors('body.0');
    $put([['text' => 'Autorizo la cirugía de {{mascota}}.']])
        ->assertJsonValidationErrors('body');
    $put([['text' => '']])->assertJsonValidationErrors('body.0.text');
    $put([['text' => 'Yo, {{tutor}} ({{tutor_doc}}), autorizo la cirugía de {{mascota}}.', 'when' => 'no_existe']])
        ->assertJsonValidationErrors('body.0.when');
    $put([['text' => 'Yo, {{tutor}} ({{tutor_doc}}), autorizo la cirugía de {{mascota}}: {{campo.procedure}}.']])->assertOk();

    // Only administrators.
    $this->freshRequest()->withToken($this->vetToken)->putJson('/api/v1/document-templates/surgery', ['body' => [['text' => 'x']]])->assertForbidden();
});

// ------------------------------------------------------------------ D3: remote signing

function sendRemote(string $docId, string $channel = 'link'): string
{
    $url = test()->freshRequest()->withToken(test()->vetToken)->postJson("/api/v1/documents/{$docId}/remote", ['channel' => $channel])->assertCreated()->json('data.url');

    return basename($url);
}

it('sends the document to sign remotely: locks the text, checks the ID digits and records how it was signed', function () {
    $doc = euthanasia();
    $token = sendRemote($doc['id']);

    // Sending locks the text: the owner signs exactly what was sent.
    $this->freshRequest()->withToken($this->vetToken)->getJson("/api/v1/documents/{$doc['id']}")
        ->assertJsonPath('data.status.value', 'signing')
        ->assertJsonPath('data.remote.channel', 'link');

    $view = $this->freshRequest()->getJson("/api/v1/public/sign/{$token}")->assertOk()->json('data');
    expect($view['owner_first_name'])->toBe('Andrés')
        ->and($view['title'])->toBe('Consentimiento informado para eutanasia')
        ->and(json_encode($view, JSON_UNESCAPED_UNICODE))->not->toContain('91345678') // never the ID: its last 4 digits are the check
        ->and(json_encode($view, JSON_UNESCAPED_UNICODE))->toContain('Cédula de ciudadanía ••••••••');

    $this->freshRequest()->postJson("/api/v1/public/sign/{$token}", ['document_last4' => '1111', 'image' => strokes(), 'accepted' => true])
        ->assertJsonValidationErrors('document_last4');
    $this->freshRequest()->postJson("/api/v1/public/sign/{$token}", ['document_last4' => '5678', 'image' => strokes(), 'accepted' => true])->assertOk();

    // Used once.
    $this->freshRequest()->getJson("/api/v1/public/sign/{$token}")->assertStatus(410);

    $detail = signAs($doc['id'], 'vet')->assertOk()->json('data');
    expect($detail['status']['value'])->toBe('signed')
        ->and(collect($detail['signature_list'])->firstWhere('role', 'owner')['method'])->toBe('remote_link');
});

it('locks the link after 5 wrong ID attempts and invalidates it when voided, resent or expired', function () {
    $doc = euthanasia();
    $token = sendRemote($doc['id']);

    foreach (range(1, 5) as $i) {
        $this->freshRequest()->postJson("/api/v1/public/sign/{$token}", ['document_last4' => '0000', 'image' => strokes(), 'accepted' => true])->assertJsonValidationErrors('document_last4');
    }
    $this->freshRequest()->postJson("/api/v1/public/sign/{$token}", ['document_last4' => '5678', 'image' => strokes(), 'accepted' => true])->assertStatus(410);

    // A new link replaces the old one.
    $second = sendRemote($doc['id']);
    $third = sendRemote($doc['id']);
    $this->freshRequest()->getJson("/api/v1/public/sign/{$second}")->assertStatus(410);
    $this->freshRequest()->getJson("/api/v1/public/sign/{$third}")->assertOk();

    // Voiding closes it.
    $this->freshRequest()->withToken($this->vetToken)->postJson("/api/v1/documents/{$doc['id']}/void", ['reason' => 'Datos equivocados'])->assertOk();
    $this->freshRequest()->getJson("/api/v1/public/sign/{$third}")->assertStatus(410);

    // After 48 h any link expires.
    $other = euthanasia();
    $late = sendRemote($other['id']);
    $this->travel(49)->hours();
    $this->freshRequest()->getJson("/api/v1/public/sign/{$late}")->assertStatus(410);
});

it('sends by email or WhatsApp only with the contact on file', function () {
    Notification::fake();
    $doc = euthanasia();

    $this->freshRequest()->withToken($this->vetToken)->postJson("/api/v1/documents/{$doc['id']}/remote", ['channel' => 'email'])->assertJsonValidationErrors('channel');

    tenant()->runAs($this->clinic->id, fn () => $this->owner->update(['email' => 'andres@example.com']));
    $this->freshRequest()->withToken($this->vetToken)->postJson("/api/v1/documents/{$doc['id']}/remote", ['channel' => 'email'])->assertCreated();
    Notification::assertSentTo(new AnonymousNotifiable, DocumentSignRequestNotification::class);

    $wa = $this->freshRequest()->withToken($this->vetToken)->postJson("/api/v1/documents/{$doc['id']}/remote", ['channel' => 'whatsapp'])->assertCreated()->json('data');
    expect($wa['whatsapp_url'])->toStartWith('https://wa.me/573004561234?text=');
});

it('lets the owner sign from the portal without the ID digits, and download it signed', function () {
    // Portal account linked to this owner (as an accepted invitation would do).
    $account = TutorAccount::create(['name' => 'Andrés', 'email' => 'andres@example.com', 'phone' => '+573004561234', 'password' => 'Clave2026x']);
    tenant()->runAs($this->clinic->id, fn () => TutorAccountOwner::create(['owner_id' => $this->owner->id, 'tutor_account_id' => $account->id, 'linked_at' => now()]));
    $session = $account->issueToken('test');

    $doc = euthanasia();
    $this->freshRequest()->withToken($session)->getJson('/api/v1/portal/documents')->assertJsonCount(0, 'data'); // a draft isn't shown

    sendRemote($doc['id']);
    $list = $this->freshRequest()->withToken($session)->getJson('/api/v1/portal/documents')->assertOk()->json('data');
    expect($list)->toHaveCount(1)->and($list[0]['to_sign'])->toBeTrue();

    $this->freshRequest()->withToken($session)->postJson("/api/v1/portal/documents/{$doc['id']}/sign", ['image' => strokes(), 'accepted' => true])->assertOk();
    $this->freshRequest()->withToken($session)->postJson("/api/v1/portal/documents/{$doc['id']}/sign", ['image' => strokes(), 'accepted' => true])->assertStatus(422);
    $done = signAs($doc['id'], 'vet')->assertOk()->json('data');
    expect(collect($done['signature_list'])->firstWhere('role', 'owner')['method'])->toBe('portal');

    $this->freshRequest()->withToken($session)->get("/api/v1/portal/documents/{$doc['id']}/pdf")->assertOk()->assertHeader('Content-Type', 'application/pdf');

    // Another owner's document: 404.
    $otherPet = makePet(makeOwner($this->clinic, ['document_number' => '123456']), ['name' => 'Ajeno']);
    $foreign = $this->freshRequest()->withToken($this->vetToken)->postJson("/api/v1/pets/{$otherPet->id}/documents", ['type' => 'hospitalization', 'data' => ['reason' => 'Vómito']])->assertCreated()->json('data.id');
    sendRemote($foreign);
    $this->freshRequest()->withToken($session)->getJson("/api/v1/portal/documents/{$foreign}")->assertNotFound();
});
