<?php

namespace App\Http\Requests\Members;

use App\Auth\ClinicRole;
use App\Http\Requests\ApiRequest;
use App\Models\Branch;
use App\Models\ClinicMembership;
use Illuminate\Validation\Rule;

class UpdateMemberRequest extends ApiRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'role' => ['sometimes', Rule::enum(ClinicRole::class)],
            'status' => ['sometimes', Rule::in([ClinicMembership::STATUS_ACTIVE, ClinicMembership::STATUS_DISABLED])],
            'shows_in_agenda' => ['sometimes', 'boolean'],
            'agenda_color' => ['sometimes', 'nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'branch_ids' => ['sometimes', 'array', 'min:1'],
            // Checked through the Branch model so only branches of this clinic pass.
            'branch_ids.*' => ['uuid', fn ($attribute, $value, $fail) => Branch::whereKey($value)->exists() ?: $fail('La sede no existe.')],
        ];
    }
}
