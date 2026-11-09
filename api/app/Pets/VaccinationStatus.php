<?php

namespace App\Pets;

use App\Models\Clinic;
use App\Models\Clinical\ClinicalRecord;
use App\Models\Pet;
use Illuminate\Support\Carbon;

/**
 * "Is this pet's vaccination up to date?": the latest application of each
 * vaccine and until when it is valid. Shown on the public card (QR), the
 * card PDF and, later, the owner portal.
 */
class VaccinationStatus
{
    /**
     * @return list<array{vaccine: string, applied_on: string, valid_until: ?string, status: string, label: string}>
     */
    private static function isRabies(string $vaccine): bool
    {
        return str_contains(mb_strtolower($vaccine), 'rabia');
    }

    public function for(Pet $pet, Clinic $clinic): array
    {
        $today = Carbon::now($clinic->timezone)->startOfDay();

        return ClinicalRecord::query()
            ->with('vaccination')
            ->where('pet_id', $pet->id)
            ->where('type', 'vaccination')
            ->notVoided()
            ->orderByDesc('occurred_at')
            ->get()
            ->filter(fn (ClinicalRecord $r) => $r->vaccination !== null)
            // Latest application per vaccine (catalog id, or the typed name).
            ->unique(fn (ClinicalRecord $r) => $r->vaccination->vaccine_id ?? mb_strtolower($r->vaccination->vaccine_name))
            ->map(function (ClinicalRecord $r) use ($today, $clinic) {
                $until = $r->next_control_at;
                $status = match (true) {
                    $until === null => 'no_booster',
                    $until->lt($today) => 'expired',
                    default => 'valid',
                };

                return [
                    'vaccine' => $r->vaccination->vaccine_name,
                    'applied_on' => $r->occurred_at->copy()->setTimezone($clinic->timezone)->toDateString(),
                    'valid_until' => $until?->toDateString(),
                    'status' => $status,
                    'label' => ['valid' => 'Vigente', 'expired' => 'Vencida', 'no_booster' => 'Sin refuerzo registrado'][$status],
                ];
            })
            // Rabies first (the one daycares, travel and authorities ask for), then the most recent.
            // The card only fits a few: what matters must come first.
            ->sortBy([
                fn ($a, $b) => self::isRabies($b['vaccine']) <=> self::isRabies($a['vaccine']),
                fn ($a, $b) => $b['applied_on'] <=> $a['applied_on'],
            ])
            ->values()
            ->all();
    }
}
