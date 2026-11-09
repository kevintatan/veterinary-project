<?php

namespace App\Http\Requests\Members;

use App\Auth\ClinicRole;
use App\Http\Requests\ApiRequest;
use App\Models\Branch;
use Illuminate\Validation\Rule;

class InviteMemberRequest extends ApiRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:255'],
            'role' => ['required', Rule::enum(ClinicRole::class)],
            'branch_ids' => ['nullable', 'array', 'min:1'],
            'branch_ids.*' => ['uuid', fn ($attribute, $value, $fail) => Branch::whereKey($value)->exists() ?: $fail('La sede no existe.')],
        ];
    }
}
