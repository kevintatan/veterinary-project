<?php

namespace App\Http\Requests\Patients;

use App\Enums\DocumentType;
use App\Http\Requests\ApiRequest;
use App\Models\Owner;
use App\Support\Phone;
use App\Tenancy\TenantContext;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class OwnerRequest extends ApiRequest
{
    protected function prepareForValidation(): void
    {
        $merge = [];

        foreach (['mobile_phone', 'phone', 'alt_contact_phone'] as $field) {
            if ($this->filled($field)) {
                // Keep the raw value when it cannot be normalized so validation reports it.
                $merge[$field] = Phone::normalize((string) $this->input($field)) ?? (string) $this->input($field);
            }
        }

        if ($this->filled('email')) {
            $merge['email'] = Str::lower(trim((string) $this->input('email')));
        }

        if ($this->filled('document_number')) {
            $merge['document_number'] = preg_replace('/[\s.\-]/', '', (string) $this->input('document_number'));
        }

        $this->merge($merge);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $creating = $this->isMethod('POST');
        /** @var Owner|null $owner */
        $owner = $this->route('owner');

        $e164 = ['nullable', 'string', 'regex:/^\+\d{8,15}$/'];

        return [
            'document_type' => ['nullable', 'required_with:document_number', Rule::enum(DocumentType::class)],
            'document_number' => [
                'nullable', 'string', 'max:20', 'regex:/^[A-Za-z0-9]+$/',
                Rule::unique('owners', 'document_number')
                    ->where('clinic_id', app(TenantContext::class)->requireId())
                    ->where('document_type', $this->input('document_type'))
                    ->whereNull('deleted_at')
                    ->ignore($owner?->id),
            ],
            'full_name' => [$creating ? 'required' : 'sometimes', 'string', 'max:150'],
            'email' => ['nullable', 'email', 'max:255'],
            'mobile_phone' => $e164,
            'phone' => $e164,
            'address' => ['nullable', 'string', 'max:255'],
            'municipality_code' => ['nullable', 'string', 'max:10'],
            'neighborhood' => ['nullable', 'string', 'max:80'],
            'alt_contact_name' => ['nullable', 'string', 'max:150'],
            'alt_contact_phone' => $e164,
            'referral_source' => ['nullable', 'string', 'max:30'],
            'important_notes' => ['nullable', 'string', 'max:2000'],

            // Habeas Data on creation: processing authorization is mandatory, channels are opt-in.
            'consents' => [$creating ? 'required' : 'prohibited', 'array'],
            'consents.data_processing' => [$creating ? 'accepted' : 'prohibited'],
            'consents.whatsapp' => ['sometimes', 'boolean'],
            'consents.sms' => ['sometimes', 'boolean'],
            'consents.email' => ['sometimes', 'boolean'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            ChannelConsentRules::check($validator, $this->input('consents', []), $this->input('mobile_phone'), $this->input('email'));
        }];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'mobile_phone.regex' => 'El celular no es válido. Usa 10 dígitos (ej. 3157482096) o el formato internacional (+57…).',
            'phone.regex' => 'El teléfono no es válido.',
            'alt_contact_phone.regex' => 'El teléfono del contacto autorizado no es válido.',
            'document_number.unique' => 'Ya existe un tutor con este documento.',
            'consents.data_processing.accepted' => 'El tutor debe autorizar el tratamiento de sus datos personales.',
        ];
    }
}
