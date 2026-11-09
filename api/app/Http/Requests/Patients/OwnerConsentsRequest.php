<?php

namespace App\Http\Requests\Patients;

use App\Http\Requests\ApiRequest;
use App\Models\Owner;
use Illuminate\Validation\Validator;

class OwnerConsentsRequest extends ApiRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'data_processing' => ['sometimes', 'boolean'],
            'whatsapp' => ['sometimes', 'boolean'],
            'sms' => ['sometimes', 'boolean'],
            'email' => ['sometimes', 'boolean'],
            'marketing' => ['sometimes', 'boolean'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            /** @var Owner $owner */
            $owner = $this->route('owner');
            ChannelConsentRules::check($validator, $this->all(), $owner->mobile_phone, $owner->email);
        }];
    }
}
