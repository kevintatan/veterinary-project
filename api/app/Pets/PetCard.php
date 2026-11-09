<?php

namespace App\Pets;

use App\Enums\PetAttributes;
use App\Http\Controllers\Api\V1\ClinicController;
use App\Models\Branch;
use App\Models\Clinic;
use App\Models\Pet;
use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Output\QRGdImagePNG;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

/**
 * What the pet card shows, in one place: the clinic's card designer and the
 * owner portal (4e) draw exactly the same card. Runs inside the pet's clinic.
 */
class PetCard
{
    public function __construct(private readonly VaccinationStatus $vaccines) {}

    /**
     * @param  string  $prefix  API path prefix for this audience: "" (staff) or "portal/" (owner portal)
     * @return array<string, mixed>
     */
    public function data(Pet $pet, Clinic $clinic, string $prefix = ''): array
    {
        $pet->loadMissing(['species', 'breed', 'owner']);
        $branch = Branch::where('is_active', true)->orderBy('created_at')->first();

        return [
            'pet' => [
                'id' => $pet->id,
                'name' => $pet->name,
                'species' => $pet->species?->name,
                'species_code' => $pet->species?->code,
                'breed' => $pet->breed?->name ?? $pet->breed_other,
                'sex' => PetAttributes::SEX[$pet->sex] ?? null,
                'birth_date' => $pet->birth_date?->toDateString(),
                'birth_date_is_estimated' => $pet->birth_date_is_estimated,
                'color' => $pet->color,
                'microchip' => $pet->microchip,
                'photo_url' => $pet->photo_path === null ? null : "{$prefix}pets/{$pet->id}/photo?v=".substr(md5($pet->photo_path), 0, 8),
                'code' => self::code($pet),
            ],
            'owner' => ['full_name' => $pet->owner?->full_name, 'mobile_phone' => $pet->owner?->mobile_phone],
            'clinic' => [
                'name' => $clinic->name,
                'phone' => $branch?->phone ?? $clinic->phone,
                // Staff read the logo from the clinic; the portal through the pet (it has no clinic token).
                'logo_url' => $prefix === '' ? ClinicController::logoUrl($clinic) : ($clinic->logo_path === null ? null : "{$prefix}pets/{$pet->id}/logo?v=".substr(md5($clinic->logo_path), 0, 8)),
            ],
            'qr_url' => "{$prefix}pets/{$pet->id}/qr",
            'public_url' => $pet->publicUrl(),
            'vaccinations' => $this->vaccines->for($pet, $clinic),
            'design' => CardDesign::forPet($pet, $clinic),
        ];
    }

    /** Short human code printed on the card: last 8 hex of the id, "1A2B-3C4D". */
    public static function code(Pet $pet): string
    {
        $hex = strtoupper(substr(str_replace('-', '', $pet->id), -8));

        return substr($hex, 0, 4).'-'.substr($hex, 4);
    }

    /** QR to the public page (PNG bytes). */
    public static function qrPng(Pet $pet, int $scale = 10): string
    {
        return (new QRCode(new QROptions([
            'outputInterface' => QRGdImagePNG::class,
            'outputBase64' => false,
            'eccLevel' => EccLevel::M, // survives a scratched, laminated card
            'scale' => $scale,
            'addQuietzone' => true,
        ])))->render($pet->publicUrl());
    }
}
