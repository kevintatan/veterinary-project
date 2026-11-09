<?php

namespace App\Http\Requests\Clinical;

use App\Clinical\RecordTypes;
use App\Http\Requests\ApiRequest;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Clinical\ClinicalRecord;
use App\Models\Pet;
use App\Support\ClinicTime;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Common fields + the payload of the record's type (RecordTypes).
 */
class ClinicalRecordRequest extends ApiRequest
{
    public function recordType(): string
    {
        /** @var ClinicalRecord|null $record */
        $record = $this->route('record');

        return $record?->type ?? (string) $this->input('type');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $creating = $this->isMethod('POST');

        $common = [
            'type' => [$creating ? 'required' : 'prohibited', Rule::in(RecordTypes::keys())],
            'occurred_at' => ['nullable', 'date'],
            'branch_id' => ['nullable', 'uuid', fn ($a, $v, $fail) => Branch::whereKey($v)->exists() ?: $fail('La sede no existe.')],
            'appointment_id' => [$creating ? 'nullable' : 'prohibited', 'uuid'],
            'parent_record_id' => [$creating ? 'nullable' : 'prohibited', 'uuid'],
            'next_control_at' => ['nullable', 'date'],
            'next_control_note' => ['nullable', 'string', 'max:255'],
            'visible_to_owner' => ['sometimes', 'boolean'],
            'ai_assisted' => ['sometimes', 'boolean'],
            // Fase 5a · V3: product of the inventory used (vaccine, deworming, procedure), only when creating.
            'product_use' => [$creating ? 'nullable' : 'prohibited', 'array'],
            'product_use.product_id' => ['required_with:product_use', 'uuid'],
            'product_use.quantity' => ['required_with:product_use', 'numeric', 'gt:0', 'max:9999'],
            'product_use.charge' => ['required_with:product_use', 'boolean'],
        ];

        if (! in_array($this->recordType(), RecordTypes::keys(), true)) {
            return $common; // "type" rule reports the error
        }

        return $common + RecordTypes::for($this->recordType())->rules($creating);
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            // Read in the clinic timezone (inputs without offset are clinic time).
            if ($this->filled('occurred_at')
                && ClinicTime::parse((string) $this->input('occurred_at'), $this->attributes->get('clinic'))->gt(now()->addMinutes(10))) {
                $validator->errors()->add('occurred_at', 'La fecha de atención no puede ser futura.');
            }

            if (! $this->isMethod('POST')) {
                return;
            }

            if ($this->filled('product_use') && ! in_array($this->recordType(), ['vaccination', 'deworming', 'procedure'], true)) {
                $validator->errors()->add('product_use', 'Solo las vacunas, desparasitaciones y procedimientos usan productos del inventario.');
            }

            /** @var Pet $pet */
            $pet = $this->route('pet');

            if ($this->filled('appointment_id')) {
                $appointment = Appointment::find($this->input('appointment_id'));

                if ($appointment === null || ($appointment->pet_id !== null && $appointment->pet_id !== $pet->id)) {
                    $validator->errors()->add('appointment_id', 'La cita no corresponde a esta mascota.');
                }
            }

            if ($this->filled('parent_record_id')) {
                $parent = ClinicalRecord::find($this->input('parent_record_id'));

                if ($parent === null || $parent->pet_id !== $pet->id || $parent->isVoided()) {
                    $validator->errors()->add('parent_record_id', 'El registro principal no corresponde a esta mascota.');
                }
            }
        }];
    }
}
