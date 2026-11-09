<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Patients\StorePetPhoto;
use App\Http\Controllers\Controller;
use App\Http\Requests\Patients\PetAlertRequest;
use App\Http\Requests\Patients\PetLifestyleRequest;
use App\Http\Requests\Patients\PetRequest;
use App\Http\Resources\PetAlertResource;
use App\Http\Resources\PetResource;
use App\Models\Pet;
use App\Models\PetAlert;
use App\Models\PetLifestyle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PetController extends Controller
{
    private const RELATIONS = ['species', 'breed', 'owner', 'lifestyle'];

    public function store(PetRequest $request): JsonResponse
    {
        $pet = Pet::create([...$request->validated(), 'created_by' => $request->user()->id]);

        return (new PetResource($pet->load(self::RELATIONS)))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Pet $pet): PetResource
    {
        return new PetResource($pet->load($this->detailRelations()));
    }

    public function storePhoto(Request $request, Pet $pet, StorePetPhoto $storePhoto): PetResource
    {
        $request->validate(['photo' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,heic', 'max:8192']], [], ['photo' => 'foto']);

        return new PetResource($storePhoto($pet, $request->file('photo'))->load($this->detailRelations()));
    }

    public function photo(Pet $pet): StreamedResponse
    {
        $disk = Storage::disk(config('filesystems.attachments_disk'));
        abort_if($pet->photo_path === null || ! $disk->exists($pet->photo_path), 404);

        // The path changes on every upload, so the response can be cached for long.
        return $disk->response($pet->photo_path, null, ['Cache-Control' => 'private, max-age=31536000, immutable']);
    }

    public function destroyPhoto(Pet $pet): PetResource
    {
        if ($pet->photo_path !== null) {
            Storage::disk(config('filesystems.attachments_disk'))->delete($pet->photo_path);
            $pet->forceFill(['photo_path' => null])->save();
        }

        return new PetResource($pet->load($this->detailRelations()));
    }

    public function update(PetRequest $request, Pet $pet): PetResource
    {
        $pet->update($request->validated());

        return new PetResource($pet->load($this->detailRelations()));
    }

    public function destroy(Pet $pet): Response
    {
        $pet->delete();

        return response()->noContent();
    }

    /**
     * Invalidates every printed card / QR of this pet.
     */
    public function regeneratePublicToken(Pet $pet): JsonResponse
    {
        $pet->forceFill(['public_token' => Pet::newPublicToken()])->save();

        return response()->json(['data' => ['public_token' => $pet->public_token]]);
    }

    public function storeAlert(PetAlertRequest $request, Pet $pet): JsonResponse
    {
        $alert = $pet->alerts()->create([...$request->validated(), 'created_by' => $request->user()->id]);

        return (new PetAlertResource($alert))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function updateAlert(PetAlertRequest $request, Pet $pet, PetAlert $alert): PetAlertResource
    {
        abort_unless($alert->pet_id === $pet->id, Response::HTTP_NOT_FOUND);

        $alert->update($request->validated());

        return new PetAlertResource($alert);
    }

    public function updateLifestyle(PetLifestyleRequest $request, Pet $pet): JsonResponse
    {
        $lifestyle = PetLifestyle::updateOrCreate(['pet_id' => $pet->id], $request->validated());

        return response()->json(['data' => $lifestyle]);
    }

    /** Same shape as GET /pets/{pet}, so clients can replace their cached copy. */
    private function detailRelations(): array
    {
        return [...self::RELATIONS, 'alerts' => fn ($q) => $q->orderByDesc('is_active')->latest()];
    }
}
