<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\PetAttributes;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Clinic;
use App\Models\Pet;
use App\Models\PetPublicView;
use App\Pets\VaccinationStatus;
use App\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The page behind a pet card's QR (docs/database.md §10b). Public, no login,
 * minimal data: who the pet is, which clinic vouches for it, whether its
 * vaccines are valid and — only in lost mode — how to reach the owner.
 * Never documents, addresses or the clinical history.
 */
class PublicPetController extends Controller
{
    public function __construct(private readonly TenantContext $tenant) {}

    public function show(Request $request, string $publicToken, VaccinationStatus $vaccines): JsonResponse
    {
        return $this->inPetClinic($publicToken, function (Pet $pet, Clinic $clinic) use ($request, $vaccines) {
            $lost = $pet->lost_mode_enabled_at !== null && $pet->deceased_at === null;
            $this->recordView($request, $pet, $lost);
            $branch = Branch::where('is_active', true)->orderBy('created_at')->first();

            return response()->json(['data' => [
                'pet' => [
                    'name' => $pet->name,
                    'species' => $pet->species?->name,
                    'breed' => $pet->breed?->name ?? $pet->breed_other,
                    'sex' => PetAttributes::SEX[$pet->sex] ?? null,
                    'color' => $pet->color,
                    'age' => $pet->ageLabel(),
                    'photo_url' => $pet->photo_path === null ? null : "public/pets/{$pet->public_token}/photo?v=".substr(md5($pet->photo_path), 0, 8),
                    'microchip' => $pet->microchip !== null, // only whether it has one
                    'deceased' => $pet->deceased_at !== null,
                ],
                'clinic' => [
                    'name' => $clinic->name,
                    'phone' => $branch?->phone ?? $clinic->phone,
                    'address' => $branch?->address ?? $clinic->address,
                ],
                'vaccinations' => $vaccines->for($pet, $clinic),
                'lost' => $lost ? [
                    'since' => $pet->lost_mode_enabled_at->toIso8601String(),
                    'owner_first_name' => Str::of((string) $pet->owner?->full_name)->explode(' ')->first(),
                    'contact_phone' => $pet->public_contact_phone ?? $pet->owner?->mobile_phone,
                ] : null,
            ]]);
        });
    }

    public function photo(string $publicToken): StreamedResponse
    {
        return $this->inPetClinic($publicToken, function (Pet $pet) {
            $disk = Storage::disk(config('filesystems.attachments_disk'));
            abort_if($pet->photo_path === null || ! $disk->exists($pet->photo_path), 404);

            return $disk->response($pet->photo_path, null, ['Cache-Control' => 'public, max-age=86400']);
        });
    }

    /**
     * Resolves the token to its clinic with a narrow query (only clinic_id),
     * then works inside that clinic like any other request.
     */
    private function inPetClinic(string $token, callable $callback): mixed
    {
        abort_unless(preg_match('/^[A-Za-z0-9]{32}$/', $token) === 1, 404);

        $clinicId = DB::table('pets')->where('public_token', $token)->whereNull('deleted_at')->value('clinic_id');
        abort_if($clinicId === null, 404);

        $clinic = Clinic::find($clinicId);
        abort_if($clinic === null || ! $clinic->isActive(), 404);

        return $this->tenant->runAs($clinic->id, function () use ($token, $clinic, $callback) {
            $pet = Pet::with(['species', 'breed', 'owner'])->where('public_token', $token)->firstOrFail();

            // The owner (or clinic) can turn the page off; lost mode always shows it.
            abort_if(! $pet->public_profile_enabled && $pet->lost_mode_enabled_at === null, 404);

            return $callback($pet, $clinic);
        });
    }

    /** One view per visitor (and mode) every 10 minutes: refreshes don't inflate the count. */
    private function recordView(Request $request, Pet $pet, bool $lost): void
    {
        $ipHash = hash('sha256', $request->ip().'|'.config('app.key'));

        $recent = PetPublicView::where('pet_id', $pet->id)
            ->where('ip_hash', $ipHash)
            ->where('in_lost_mode', $lost) // a scan right after "lost" was turned on always counts
            ->where('viewed_at', '>=', now()->subMinutes(10))
            ->exists();

        if (! $recent) {
            PetPublicView::create([
                'pet_id' => $pet->id,
                'in_lost_mode' => $lost,
                'ip_hash' => $ipHash,
                'user_agent' => Str::limit((string) $request->userAgent(), 250, ''),
                'viewed_at' => now(),
            ]);
        }
    }
}
