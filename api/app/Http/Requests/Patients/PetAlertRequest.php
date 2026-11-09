<?php

namespace App\Http\Requests\Patients;

use App\Enums\PetAttributes;
use App\Http\Requests\ApiRequest;
use App\Models\Catalogs\ClinicalAlert;
use Illuminate\Validation\Rule;

class PetAlertRequest extends ApiRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $creating = $this->isMethod('POST');

        return [
            'type' => [$creating ? 'required' : 'sometimes', Rule::in(array_keys(PetAttributes::ALERT_TYPE))],
            'clinical_alert_id' => ['nullable', 'uuid', fn ($a, $v, $fail) => ClinicalAlert::whereKey($v)->exists() ?: $fail('La alerta no existe en el catálogo.')],
            'description' => [$creating ? 'required' : 'sometimes', 'string', 'max:255'],
            'severity' => ['sometimes', Rule::in(array_keys(PetAttributes::ALERT_SEVERITY))],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
