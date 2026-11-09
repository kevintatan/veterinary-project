<?php

namespace App\Http\Requests;

use App\Models\ClinicMembership;
use App\Models\Owner;
use App\Models\Pet;
use App\Models\Task;
use App\Support\ClinicTime;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class TaskRequest extends ApiRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $creating = $this->isMethod('POST');

        return [
            'title' => [$creating ? 'required' : 'sometimes', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:5000'],
            'owner_id' => ['nullable', 'uuid', fn ($a, $v, $fail) => Owner::whereKey($v)->exists() ?: $fail('El tutor no existe.')],
            'pet_id' => ['nullable', 'uuid', fn ($a, $v, $fail) => Pet::whereKey($v)->exists() ?: $fail('La mascota no existe.')],
            'assignee_id' => ['nullable', 'uuid', fn ($a, $v, $fail) => ClinicMembership::where('user_id', $v)->where('status', ClinicMembership::STATUS_ACTIVE)->exists() ?: $fail('El responsable no pertenece a la clínica.')],
            'due_at' => ['nullable', 'date'],
            'status' => [$creating ? 'prohibited' : 'sometimes', Rule::in(array_keys(Task::STATUSES))],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->isNotEmpty() || ! $this->filled('pet_id') || ! $this->filled('owner_id')) {
                return;
            }

            if (Pet::find($this->input('pet_id'))?->owner_id !== $this->input('owner_id')) {
                $validator->errors()->add('pet_id', 'La mascota no pertenece a ese tutor.');
            }
        }];
    }

    /**
     * @return array<string, mixed>
     */
    public function taskData(): array
    {
        $data = $this->validated();

        if (! empty($data['due_at'])) {
            $data['due_at'] = ClinicTime::parse((string) $data['due_at'], $this->attributes->get('clinic'));
        }

        // A pet implies its owner.
        if (! empty($data['pet_id']) && empty($data['owner_id'])) {
            $data['owner_id'] = Pet::find($data['pet_id'])?->owner_id;
        }

        return $data;
    }
}
