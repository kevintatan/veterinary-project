<?php

namespace App\Actions\Patients;

use App\Enums\ConsentType;
use App\Models\Clinic;
use App\Models\Owner;
use App\Models\OwnerConsent;

/**
 * Appends Habeas Data evidence for the consents whose state changes.
 * Unchanged values create no rows, so the log reads as a real history.
 */
final class RecordConsents
{
    /**
     * @param  array<string, bool>  $changes  keys: data_processing, whatsapp, sms, email, marketing
     * @param  array<string, mixed>|null  $evidence
     * @return list<string> consent types that changed
     */
    public function __invoke(Owner $owner, array $changes, string $source, ?string $recordedBy, ?array $evidence = null): array
    {
        $current = $owner->currentConsents();
        $clinic = Clinic::find($owner->clinic_id);
        $changed = [];

        foreach ($changes as $key => $granted) {
            $type = $this->typeFor($key);
            $granted = (bool) $granted;

            if ($type === null || $current[$type->value] === $granted) {
                continue;
            }

            OwnerConsent::create([
                'owner_id' => $owner->id,
                'type' => $type->value,
                'granted' => $granted,
                'policy_version' => (string) ($clinic?->setting('privacy_policy_version') ?? '1'),
                'source' => $source,
                'recorded_by' => $recordedBy,
                'evidence' => $evidence,
            ]);

            $changed[] = $type->value;
        }

        return $changed;
    }

    private function typeFor(string $key): ?ConsentType
    {
        return ConsentType::tryFrom($key) ?? ConsentType::tryFrom('channel_'.$key);
    }
}
