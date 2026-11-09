<?php

namespace App\Http\Requests\Agenda;

use App\Http\Requests\ApiRequest;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Clinic;
use App\Models\ClinicMembership;
use App\Models\Owner;
use App\Models\Pet;
use App\Models\Service;
use App\Support\ClinicTime;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Validator;

class AppointmentRequest extends ApiRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $creating = $this->isMethod('POST');
        $required = $creating ? 'required' : 'sometimes';
        $exists = fn (string $model, string $message) => fn ($a, $v, $fail) => $model::whereKey($v)->exists() ?: $fail($message);

        return [
            'branch_id' => [$required, 'uuid', fn ($a, $v, $fail) => Branch::whereKey($v)->where('is_active', true)->exists() ?: $fail('La sede no existe o está inactiva.')],
            'owner_id' => ['nullable', 'uuid', $exists(Owner::class, 'El tutor no existe.')],
            'pet_id' => ['nullable', 'uuid', $exists(Pet::class, 'La mascota no existe.')],
            'service_id' => ['nullable', 'uuid', fn ($a, $v, $fail) => Service::whereKey($v)->where('is_active', true)->exists() ?: $fail('El servicio no existe o está inactivo.')],
            'starts_at' => [$required, 'date'],
            'ends_at' => ['nullable', 'date'],
            'user_ids' => [$required, 'array', 'min:1', 'max:5'],
            'user_ids.*' => ['uuid', 'distinct'],
            'is_time_unset' => ['sometimes', 'boolean'],
            'is_home_visit' => ['sometimes', 'boolean'],
            'home_address' => ['nullable', 'required_if:is_home_visit,true', 'string', 'max:255'],
            'notes_for_owner' => ['nullable', 'string', 'max:2000'],
            'internal_notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->isNotEmpty() || ! $this->has('user_ids')) {
                return;
            }

            /** @var Appointment|null $appointment */
            $appointment = $this->route('appointment');
            $branchId = $this->input('branch_id', $appointment?->branch_id);

            foreach ($this->input('user_ids') as $userId) {
                $isActiveMember = ClinicMembership::where('user_id', $userId)->where('status', ClinicMembership::STATUS_ACTIVE)->exists();
                $worksInBranch = DB::table('branch_user')->where('branch_id', $branchId)->where('user_id', $userId)->exists();

                if (! $isActiveMember || ! $worksInBranch) {
                    $validator->errors()->add('user_ids', 'Uno de los encargados no trabaja en esta sede.');

                    return;
                }
            }
        }];
    }

    /**
     * Validated data with times converted to UTC (inputs without offset are clinic time).
     *
     * @return array<string, mixed>
     */
    public function appointmentData(Clinic $clinic): array
    {
        $data = $this->validated();

        foreach (['starts_at', 'ends_at'] as $field) {
            if (! empty($data[$field])) {
                $data[$field] = ClinicTime::parse((string) $data[$field], $clinic);
            }
        }

        return $data;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['home_address.required_if' => 'Indica la dirección de la visita a domicilio.'];
    }
}
