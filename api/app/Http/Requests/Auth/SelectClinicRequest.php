<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\ApiRequest;

class SelectClinicRequest extends ApiRequest
{
    /**
     * Membership is verified by IssueClinicToken, not here: a "does not exist"
     * validation error would reveal which clinic ids exist.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'clinic_id' => ['required', 'uuid'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ];
    }
}
