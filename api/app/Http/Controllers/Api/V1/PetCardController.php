<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Clinic;
use App\Models\Pet;
use App\Models\PetPublicView;
use App\Pets\CardDesign;
use App\Pets\PetCard;
use App\Support\ClinicTime;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Digital pet card (Fase 4b): QR to the public page, printable card PDF,
 * lost mode and the scans log.
 */
class PetCardController extends Controller
{
    /**
     * Everything the card designer needs (web/src/components/pet-card): the pet,
     * owner and clinic data, vaccine status and the design to draw.
     */
    public function card(Request $request, Pet $pet, PetCard $card): JsonResponse
    {
        $clinic = $this->clinic($request);

        return response()->json(['data' => [
            ...$card->data($pet, $clinic),
            'clinic_design' => CardDesign::forClinic($clinic),
            'has_own_design' => $pet->card_design !== null,
        ]]);
    }

    /** Saves the pet's design (null = go back to the clinic's default). */
    public function updateDesign(Request $request, Pet $pet): JsonResponse
    {
        if ($request->input('design') === null) {
            $pet->forceFill(['card_design' => null])->save();
        } else {
            $data = $request->validate(['design' => ['required', 'array'], ...CardDesign::rules('design.')], [], ['design.color' => 'color']);
            $pet->forceFill(['card_design' => CardDesign::only($data['design'])])->save();
        }

        return response()->json(['data' => ['design' => CardDesign::forPet($pet, $this->clinic($request)), 'has_own_design' => $pet->card_design !== null]]);
    }

    /** Short, human code printed on the card ("N.º 4F2A-9C1B"): not a secret, just a reference. */
    public static function code(Pet $pet): string
    {
        return PetCard::code($pet);
    }

    public function qr(Pet $pet): Response
    {
        return response($this->qrPng($pet, 10), 200, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'private, max-age=300',
        ]);
    }

    /**
     * Lost mode: the public page shows the owner's contact and a notice.
     */
    public function lostMode(Request $request, Pet $pet): JsonResponse
    {
        $data = $request->validate([
            'enabled' => ['required', 'boolean'],
            'contact_phone' => ['nullable', 'string', 'regex:/^\+\d{8,15}$/'],
        ], [], ['contact_phone' => 'teléfono de contacto']);

        abort_if($data['enabled'] && $pet->deceased_at !== null, 422, 'La mascota está registrada como fallecida.');

        $pet->forceFill([
            'lost_mode_enabled_at' => $data['enabled'] ? ($pet->lost_mode_enabled_at ?? now()) : null,
            'public_contact_phone' => $data['contact_phone'] ?? null,
        ])->save();

        activity('patients')->performedOn($pet)->causedBy($request->user())
            ->event($data['enabled'] ? 'lost_mode_on' : 'lost_mode_off')
            ->log($data['enabled'] ? 'Activó el modo mascota perdida' : 'Desactivó el modo mascota perdida');

        return response()->json(['data' => $this->state($pet, $this->clinic($request))]);
    }

    public function state(Pet $pet, Clinic $clinic): array
    {
        return [
            'public_url' => $pet->publicUrl(),
            'public_profile_enabled' => $pet->public_profile_enabled,
            'lost_mode_since' => ClinicTime::iso($pet->lost_mode_enabled_at, $clinic),
            'public_contact_phone' => $pet->public_contact_phone,
        ];
    }

    public function views(Request $request, Pet $pet): JsonResponse
    {
        $clinic = $this->clinic($request);
        $views = PetPublicView::where('pet_id', $pet->id)->latest('viewed_at')->limit(20)->get();

        return response()->json(['data' => [
            ...$this->state($pet, $clinic),
            'total' => PetPublicView::where('pet_id', $pet->id)->count(),
            'views' => $views->map(fn (PetPublicView $v) => [
                'viewed_at' => ClinicTime::iso($v->viewed_at, $clinic),
                'in_lost_mode' => $v->in_lost_mode,
                'device' => $this->device($v->user_agent),
            ]),
        ]]);
    }

    private function qrPng(Pet $pet, int $scale): string
    {
        return PetCard::qrPng($pet, $scale);
    }

    private function device(?string $userAgent): string
    {
        $ua = (string) $userAgent;

        return match (true) {
            str_contains($ua, 'iPhone') || str_contains($ua, 'iPad') => 'iPhone / iPad',
            str_contains($ua, 'Android') => 'Android',
            $ua === '' => 'Desconocido',
            default => 'Computador',
        };
    }

    private function clinic(Request $request): Clinic
    {
        return $request->attributes->get('clinic');
    }
}
