<?php

namespace App\Http\Requests\Agenda;

use App\Http\Requests\ApiRequest;
use App\Models\Service;
use App\Tenancy\TenantContext;
use Illuminate\Validation\Rule;

class ServiceRequest extends ApiRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $required = $this->isMethod('POST') ? 'required' : 'sometimes';
        /** @var Service|null $service */
        $service = $this->route('service');

        return [
            'name' => [
                $required, 'string', 'max:120',
                Rule::unique('services', 'name')->where('clinic_id', app(TenantContext::class)->requireId())->ignore($service?->id),
            ],
            'category' => [$required, Rule::in(array_keys(Service::CATEGORIES))],
            'duration_minutes' => [$required, 'integer', 'min:5', 'max:720'],
            'price' => ['sometimes', 'numeric', 'min:0', 'max:999999999'],
            'tax_rate' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'is_bookable_online' => ['sometimes', 'boolean'],
            'color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
