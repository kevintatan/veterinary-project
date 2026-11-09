<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Agenda\AvailableSlots;
use App\Actions\Agenda\ChangeAppointmentStatus;
use App\Actions\Agenda\ScheduleAppointment;
use App\Enums\AppointmentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Agenda\AppointmentRequest;
use App\Http\Resources\AppointmentResource;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Clinic;
use App\Models\ClinicMembership;
use App\Models\Service;
use App\Support\ClinicTime;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AppointmentController extends Controller
{
    private const RELATIONS = ['service', 'pet.species', 'owner', 'users'];

    private const MAX_RANGE_DAYS = 62;

    /**
     * Agenda view: appointments in a date range (clinic dates, inclusive).
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
            'branch_id' => ['nullable', 'uuid'],
            'user_id' => ['nullable', 'uuid'],
            'pet_id' => ['nullable', 'uuid'],
            'status' => ['nullable', Rule::enum(AppointmentStatus::class)],
        ], [], trans('fields'));

        $clinic = $this->clinic($request);
        $from = Carbon::parse($filters['from'], $clinic->timezone)->startOfDay();
        $to = Carbon::parse($filters['to'], $clinic->timezone)->endOfDay();

        if ($from->diffInDays($to) > self::MAX_RANGE_DAYS) {
            throw ValidationException::withMessages(['to' => 'El rango máximo es de '.self::MAX_RANGE_DAYS.' días.']);
        }

        $appointments = Appointment::query()
            ->with(self::RELATIONS)
            ->overlapping($from->utc(), $to->utc())
            ->when($filters['branch_id'] ?? null, fn ($q, $id) => $q->where('branch_id', $id))
            ->when($filters['pet_id'] ?? null, fn ($q, $id) => $q->where('pet_id', $id))
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['user_id'] ?? null, fn ($q, $id) => $q->whereHas('users', fn ($u) => $u->where('users.id', $id)))
            ->orderBy('starts_at')
            ->get();

        return AppointmentResource::collection($appointments);
    }

    public function store(AppointmentRequest $request, ScheduleAppointment $schedule): JsonResponse
    {
        $clinic = $this->clinic($request);
        $appointment = $schedule($clinic, $request->appointmentData($clinic), $request->user());

        return (new AppointmentResource($appointment->load(self::RELATIONS)))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Appointment $appointment): AppointmentResource
    {
        return new AppointmentResource($appointment->load([...self::RELATIONS, 'statusChanges.changedBy']));
    }

    /**
     * Reschedule / reassign. Closed appointments cannot change.
     */
    public function update(AppointmentRequest $request, Appointment $appointment, ScheduleAppointment $schedule): AppointmentResource
    {
        if ($appointment->status->isClosed()) {
            throw ValidationException::withMessages(['status' => 'No se puede modificar una cita '.mb_strtolower($appointment->status->label()).'.']);
        }

        $clinic = $this->clinic($request);
        $data = $request->appointmentData($clinic) + [
            'branch_id' => $appointment->branch_id,
            'starts_at' => $appointment->starts_at,
            'user_ids' => $appointment->users()->pluck('users.id')->all(),
        ];

        // Moving the start without a new end keeps the original duration;
        // not touching times at all keeps them exactly as they are.
        if (! $request->has('ends_at')) {
            $data['ends_at'] = $request->has('starts_at')
                ? $data['starts_at']->copy()->addMinutes($appointment->starts_at->diffInMinutes($appointment->ends_at))
                : $appointment->ends_at;
        }

        return new AppointmentResource($schedule($clinic, $data, $request->user(), $appointment)->load(self::RELATIONS));
    }

    public function changeStatus(Request $request, Appointment $appointment, ChangeAppointmentStatus $changeStatus): AppointmentResource
    {
        $data = $request->validate([
            'status' => ['required', Rule::enum(AppointmentStatus::class)],
            'note' => ['nullable', 'string', 'max:255'],
            'cancellation_reason' => ['nullable', 'string', 'max:255'],
        ], [], trans('fields'));

        $changeStatus(
            $appointment,
            AppointmentStatus::from($data['status']),
            'staff',
            $request->user()->id,
            $data['note'] ?? null,
            $data['cancellation_reason'] ?? null,
        );

        return new AppointmentResource($appointment->load([...self::RELATIONS, 'statusChanges.changedBy']));
    }

    /**
     * Agenda columns: active members shown in the agenda who work in the branch.
     */
    public function professionals(Request $request): JsonResponse
    {
        $data = $request->validate([
            'branch_id' => ['required', 'uuid', fn ($a, $v, $fail) => Branch::whereKey($v)->exists() ?: $fail('La sede no existe.')],
        ], [], trans('fields'));

        $memberships = ClinicMembership::query()
            ->where('status', ClinicMembership::STATUS_ACTIVE)
            ->where('shows_in_agenda', true)
            ->whereHas('user.branches', fn ($q) => $q->where('branches.id', $data['branch_id']))
            ->with('user')
            ->get()
            ->sortBy('user.name')
            ->values();

        return response()->json(['data' => $memberships->map(fn (ClinicMembership $m) => [
            'id' => $m->user->id,
            'name' => $m->user->name,
            'agenda_color' => $m->agenda_color,
        ])]);
    }

    public function availability(Request $request, AvailableSlots $availableSlots): JsonResponse
    {
        $data = $request->validate([
            'branch_id' => ['required', 'uuid', fn ($a, $v, $fail) => Branch::whereKey($v)->exists() ?: $fail('La sede no existe.')],
            'date' => ['required', 'date_format:Y-m-d'],
            'service_id' => ['nullable', 'uuid', fn ($a, $v, $fail) => Service::whereKey($v)->exists() ?: $fail('El servicio no existe.')],
            'user_id' => ['nullable', 'uuid'],
        ], [], trans('fields'));

        $clinic = $this->clinic($request);
        $minutes = isset($data['service_id'])
            ? Service::findOrFail($data['service_id'])->duration_minutes
            : (int) $clinic->setting('default_appointment_minutes');

        $result = $availableSlots($clinic, $data['branch_id'], $data['date'], $minutes, $data['user_id'] ?? null);

        return response()->json(['data' => array_map(fn (array $row) => [
            'user' => $row['user'],
            'slots' => array_map(fn (array $slot) => [
                'starts_at' => ClinicTime::iso($slot['starts_at'], $clinic),
                'ends_at' => ClinicTime::iso($slot['ends_at'], $clinic),
            ], $row['slots']),
        ], $result)]);
    }

    private function clinic(Request $request): Clinic
    {
        return $request->attributes->get('clinic');
    }
}
