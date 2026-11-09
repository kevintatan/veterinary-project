<?php

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake(config('filesystems.attachments_disk'));
    [$this->clinic, $this->admin] = clinicWithMember();
    $this->token = clinicToken($this->admin, $this->clinic);
    $this->pet = makePet(makeOwner($this->clinic));
});

function uploadPhoto(string $token, string $petId, UploadedFile $file)
{
    return test()->freshRequest()->withToken($token)->post("/api/v1/pets/{$petId}/photo", ['photo' => $file], ['Accept' => 'application/json']);
}

it('re-encodes the photo to a WebP of at most 800 px inside the clinic folder', function () {
    $url = uploadPhoto($this->token, $this->pet->id, UploadedFile::fake()->image('luna.jpg', 2400, 1600))
        ->assertOk()
        ->assertJsonStructure(['data' => ['alerts', 'owner']]) // same shape as GET /pets/{pet}
        ->json('data.photo_url');

    $path = $this->pet->fresh()->photo_path;
    expect($url)->toStartWith("pets/{$this->pet->id}/photo?v=")
        ->and($path)->toStartWith("clinics/{$this->clinic->id}/pets/{$this->pet->id}/")->toEndWith('.webp');

    [$width, $height] = getimagesizefromstring(Storage::disk(config('filesystems.attachments_disk'))->get($path));
    expect([$width, $height])->toBe([800, 533]);

    $this->freshRequest()->withToken($this->token)->get("/api/v1/{$url}")->assertOk()->assertHeader('Content-Type', 'image/webp');
});

it('replaces the previous file and can remove the photo', function () {
    uploadPhoto($this->token, $this->pet->id, UploadedFile::fake()->image('a.png', 300, 300))->assertOk();
    $first = $this->pet->fresh()->photo_path;

    uploadPhoto($this->token, $this->pet->id, UploadedFile::fake()->image('b.png', 300, 300))->assertOk();
    Storage::disk(config('filesystems.attachments_disk'))->assertMissing($first);

    $this->freshRequest()->withToken($this->token)->deleteJson("/api/v1/pets/{$this->pet->id}/photo")->assertOk()->assertJsonPath('data.photo_url', null);
    $this->freshRequest()->withToken($this->token)->get("/api/v1/pets/{$this->pet->id}/photo")->assertNotFound();
});

it('rejects files that are not images', function () {
    uploadPhoto($this->token, $this->pet->id, UploadedFile::fake()->create('virus.jpg', 10, 'image/jpeg'))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('photo');
});

it('keeps photos isolated between clinics', function () {
    uploadPhoto($this->token, $this->pet->id, UploadedFile::fake()->image('a.png', 100, 100))->assertOk();

    [$otherClinic, $stranger] = clinicWithMember();
    $this->freshRequest()->withToken(clinicToken($stranger, $otherClinic))->get("/api/v1/pets/{$this->pet->id}/photo")->assertNotFound();
    uploadPhoto(clinicToken($stranger, $otherClinic), $this->pet->id, UploadedFile::fake()->image('b.png', 100, 100))->assertNotFound();
});
