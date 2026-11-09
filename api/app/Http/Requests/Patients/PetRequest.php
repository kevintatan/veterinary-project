<?php

namespace App\Http\Requests\Patients;

use App\Enums\PetAttributes;
use App\Http\Requests\ApiRequest;
use App\Models\Catalogs\Breed;
use App\Models\Catalogs\Species;
use App\Models\Owner;
use App\Models\Pet;
use App\Support\Phone;
use App\Tenancy\TenantContext;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class PetRequest extends ApiRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->filled('public_contact_phone')) {
            $raw = (string) $this->input('public_contact_phone');
            $this->merge(['public_contact_phone' => Phone::normalize($raw) ?? $raw]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $creating = $this->isMethod('POST');
        $required = $creating ? 'required' : 'sometimes';
        /** @var Pet|null $pet */
        $pet = $this->route('pet');

        // Existence checked through the models so the tenant/catalog scopes apply.
        $ownerExists = fn ($a, $v, $fail) => Owner::whereKey($v)->exists() ?: $fail('El tutor no existe.');
        $speciesExists = fn ($a, $v, $fail) => Species::whereKey($v)->exists() ?: $fail('La especie no existe.');

        return [
            'owner_id' => [$required, 'uuid', $ownerExists],
            'name' => [$required, 'string', 'max:80'],
            'species_id' => [$required, 'uuid', $speciesExists],
            'breed_id' => ['nullable', 'uuid'],
            'breed_other' => ['nullable', 'string', 'max:80'],
            'sex' => ['sometimes', Rule::in(array_keys(PetAttributes::SEX))],
            'birth_date' => ['nullable', 'date', 'before_or_equal:today', 'after:1970-01-01'],
            'birth_date_is_estimated' => ['sometimes', 'boolean'],
            'color' => ['nullable', 'string', 'max:60'],
            'size' => ['nullable', Rule::in(array_keys(PetAttributes::SIZE))],
            'reproductive_status' => ['sometimes', Rule::in(array_keys(PetAttributes::REPRODUCTIVE_STATUS))],
            'temperament' => ['nullable', Rule::in(array_keys(PetAttributes::TEMPERAMENT))],
            'diet' => ['nullable', 'string', 'max:120'],
            'microchip' => [
                'nullable', 'string', 'max:30',
                Rule::unique('pets', 'microchip')
                    ->where('clinic_id', app(TenantContext::class)->requireId())
                    ->whereNull('deleted_at')
                    ->ignore($pet?->id),
            ],
            'weight_kg' => ['nullable', 'numeric', 'gt:0', 'max:2000'],
            'is_service_animal' => ['sometimes', 'boolean'],
            'is_emotional_support' => ['sometimes', 'boolean'],
            'deceased_at' => ['nullable', 'date', 'before_or_equal:today'],
            'deceased_reason' => ['nullable', 'string', 'max:255'],
            'important_notes' => ['nullable', 'string', 'max:2000'],
            'public_profile_enabled' => ['sometimes', 'boolean'],
            'public_contact_phone' => ['nullable', 'string', 'regex:/^\+\d{8,15}$/'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            if (! $this->filled('breed_id') || $validator->errors()->isNotEmpty()) {
                return;
            }

            /** @var Pet|null $pet */
            $pet = $this->route('pet');
            $speciesId = $this->input('species_id', $pet?->species_id);
            $breed = Breed::find($this->input('breed_id'));

            if ($breed === null || $breed->species_id !== $speciesId) {
                $validator->errors()->add('breed_id', 'La raza no corresponde a la especie.');
            }
        }];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'microchip.unique' => 'Ya existe una mascota con este microchip.',
            'public_contact_phone.regex' => 'El teléfono de contacto no es válido.',
        ];
    }
}
