<?php

namespace App\Http\Controllers\Api\V1\Portal;

use App\Http\Controllers\Controller;
use App\Models\Clinic;
use App\Models\Pet;
use App\Pets\PetCard;
use App\Portal\TutorPortal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** The owner's pets in the portal, from every clinic that linked them. */
class PortalPetController extends Controller
{
    public function __construct(private readonly TutorPortal $portal) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->portal->pets($request->user())]);
    }

    /** Card (same data as the clinic's card designer) + upcoming appointments. */
    public function show(Request $request, string $portalPet, PetCard $card): JsonResponse
    {
        return $this->portal->withPet($request->user(), $portalPet, function (Pet $pet, Clinic $clinic) use ($card) {
            return response()->json(['data' => [
                ...$card->data($pet, $clinic, 'portal/'),
                'deceased' => $pet->deceased_at !== null,
                'upcoming' => $this->portal->upcoming($pet, $clinic),
            ]]);
        });
    }

    public function logo(Request $request, string $portalPet): StreamedResponse
    {
        return $this->portal->withPet($request->user(), $portalPet, function (Pet $pet, Clinic $clinic) {
            $disk = Storage::disk(config('filesystems.attachments_disk'));
            abort_if($clinic->logo_path === null || ! $disk->exists($clinic->logo_path), 404);

            return $disk->response($clinic->logo_path, null, ['Cache-Control' => 'private, max-age=31536000, immutable']);
        });
    }

    public function photo(Request $request, string $portalPet): StreamedResponse
    {
        return $this->portal->withPet($request->user(), $portalPet, function (Pet $pet) {
            $disk = Storage::disk(config('filesystems.attachments_disk'));
            abort_if($pet->photo_path === null || ! $disk->exists($pet->photo_path), 404);

            return $disk->response($pet->photo_path, null, ['Cache-Control' => 'private, max-age=86400']);
        });
    }

    public function qr(Request $request, string $portalPet): Response
    {
        return $this->portal->withPet($request->user(), $portalPet, fn (Pet $pet) => response(PetCard::qrPng($pet), 200, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'private, max-age=300',
        ]));
    }
}
