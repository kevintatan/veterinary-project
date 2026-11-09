<?php

namespace App\Http\Requests\Agenda;

use App\Http\Requests\ApiRequest;
use App\Models\Branch;
use App\Models\ClinicMembership;

class ScheduleBlockRequest extends ApiRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'branch_id' => ['nullable', 'uuid', fn ($a, $v, $fail) => Branch::whereKey($v)->exists() ?: $fail('La sede no existe.')],
            'user_id' => ['nullable', 'uuid', fn ($a, $v, $fail) => ClinicMembership::where('user_id', $v)->exists() ?: $fail('El profesional no pertenece a la clínica.')],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'reason' => ['required', 'string', 'max:150'],
        ];
    }
}
