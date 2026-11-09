<?php

namespace App\Actions\Clinical;

use App\Clinical\RecordTypes;
use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\Clinical\ClinicalRecord;
use App\Models\Pet;
use App\Models\User;
use App\Sales\CareCharges;
use App\Support\ClinicTime;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Creates or updates a clinical record: common fields on the parent,
 * type-specific payload through its RecordType. Always in one transaction.
 */
final class SaveClinicalRecord
{
    public function __construct(private readonly CareCharges $charges) {}

    /**
     * @param  array<string, mixed>  $data  validated ClinicalRecordRequest
     */
    public function __invoke(Clinic $clinic, User $author, Pet $pet, array $data, ?ClinicalRecord $record = null): ClinicalRecord
    {
        $creating = $record === null;
        $type = RecordTypes::for($creating ? $data['type'] : $record->type);

        return DB::transaction(function () use ($clinic, $author, $pet, $data, $record, $creating, $type) {
            $common = Arr::only($data, ['next_control_at', 'next_control_note', 'visible_to_owner', 'ai_assisted', 'branch_id']);

            if (! empty($data['occurred_at'])) {
                $common['occurred_at'] = ClinicTime::parse((string) $data['occurred_at'], $clinic);
            }

            if ($creating) {
                $appointment = empty($data['appointment_id']) ? null : Appointment::findOrFail($data['appointment_id']);

                $record = new ClinicalRecord([
                    ...$common,
                    'type' => $type->key(),
                    'pet_id' => $pet->id,
                    'author_id' => $author->id,
                    'appointment_id' => $appointment?->id,
                    'parent_record_id' => $data['parent_record_id'] ?? null,
                    'branch_id' => $common['branch_id'] ?? $appointment?->branch_id ?? $this->defaultBranch($author),
                    'occurred_at' => $common['occurred_at'] ?? now(),
                    'visible_to_owner' => $common['visible_to_owner'] ?? in_array($type->key(), ClinicalRecord::VISIBLE_TO_OWNER_BY_DEFAULT, true),
                ]);
                $record->save();
            } else {
                $record->update($common);
            }

            $type->save($record, $data, $creating);

            // Product used (V3): to the owner's account, or only out of stock. Same transaction.
            if ($creating && ! empty($data['product_use'])) {
                $this->charges->useProduct($record, $data['product_use']['product_id'], (float) $data['product_use']['quantity'], (bool) $data['product_use']['charge'], $author, $clinic);
            }

            if ($creating && ! array_key_exists('next_control_at', $data)) {
                $suggested = $type->defaultNextControl($record, $data);

                if ($suggested !== null) {
                    $record->update(['next_control_at' => $suggested]);
                }
            }

            return $record->refresh();
        });
    }

    /**
     * The author's only branch; with several, the request must say which.
     */
    private function defaultBranch(User $author): string
    {
        $branches = $author->branches()->where('is_active', true)->pluck('branches.id');

        if ($branches->count() !== 1) {
            throw ValidationException::withMessages(['branch_id' => 'Indica la sede donde se realizó la atención.']);
        }

        return $branches->first();
    }
}
