<?php

namespace App\Http\Requests\Agenda;

use App\Http\Requests\ApiRequest;
use App\Models\Branch;
use App\Models\ClinicMembership;
use Illuminate\Validation\Validator;

/**
 * Replaces the whole weekly schedule of a branch (or of one professional in it).
 */
class ScheduleRequest extends ApiRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'branch_id' => ['required', 'uuid', fn ($a, $v, $fail) => Branch::whereKey($v)->exists() ?: $fail('La sede no existe.')],
            'user_id' => ['nullable', 'uuid', fn ($a, $v, $fail) => ClinicMembership::where('user_id', $v)->exists() ?: $fail('El profesional no pertenece a la clínica.')],
            'ranges' => ['present', 'array', 'max:50'],
            'ranges.*.weekday' => ['required', 'integer', 'between:1,7'],
            'ranges.*.start_time' => ['required', 'date_format:H:i'],
            'ranges.*.end_time' => ['required', 'date_format:H:i', 'after:ranges.*.start_time'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            // Ranges of the same day must not overlap.
            $byDay = collect($this->input('ranges'))->groupBy('weekday');

            foreach ($byDay as $ranges) {
                $sorted = $ranges->sortBy('start_time')->values();

                for ($i = 1; $i < $sorted->count(); $i++) {
                    if ($sorted[$i]['start_time'] < $sorted[$i - 1]['end_time']) {
                        $validator->errors()->add('ranges', 'Hay franjas que se cruzan en el mismo día.');

                        return;
                    }
                }
            }
        }];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['ranges.*.end_time.after' => 'La hora de cierre debe ser posterior a la de apertura.'];
    }
}
