<?php

namespace App\Http\Requests\Patients;

use App\Http\Requests\ApiRequest;

class PetLifestyleRequest extends ApiRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'housing' => ['nullable', 'string', 'max:120'],
            'food_amount' => ['nullable', 'string', 'max:60'],
            'feeding_frequency' => ['nullable', 'string', 'max:60'],
            'bath_frequency' => ['nullable', 'string', 'max:60'],
            'other_pets' => ['nullable', 'string', 'max:255'],
            'last_heat_date' => ['nullable', 'date', 'before_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
