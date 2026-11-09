<?php

namespace App\Pets;

use App\Models\Clinic;
use App\Models\Pet;
use Illuminate\Validation\Rule;

/**
 * Pet card design: one of the templates drawn by the web app
 * (web/src/components/pet-card), a color and what to show.
 * Resolution: the pet's own design → the clinic's default → Zooetia's default.
 */
final class CardDesign
{
    public const TEMPLATES = ['classic', 'modern', 'paws'];

    public const DEFAULT = [
        'template' => 'classic',
        'color' => '#166534',
        'show_photo' => true,
        'show_owner_phone' => false, // printed card: the owner decides whether their phone travels with it
        'show_vaccines' => true,
    ];

    /** @return array<string, list<mixed>> */
    public static function rules(string $prefix = ''): array
    {
        return [
            "{$prefix}template" => ['required', Rule::in(self::TEMPLATES)],
            "{$prefix}color" => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            "{$prefix}show_photo" => ['required', 'boolean'],
            "{$prefix}show_owner_phone" => ['required', 'boolean'],
            "{$prefix}show_vaccines" => ['required', 'boolean'],
        ];
    }

    /** @return array<string, mixed> */
    public static function forClinic(Clinic $clinic): array
    {
        return array_merge(self::DEFAULT, (array) ($clinic->settings['pet_card'] ?? []));
    }

    /** @return array<string, mixed> */
    public static function forPet(Pet $pet, Clinic $clinic): array
    {
        return array_merge(self::forClinic($clinic), (array) ($pet->card_design ?? []));
    }

    /** @param array<string, mixed> $design */
    public static function only(array $design): array
    {
        return array_intersect_key($design, self::DEFAULT);
    }
}
