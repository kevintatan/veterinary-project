<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Agenda\ScheduleBlockRequest;
use App\Http\Requests\Agenda\ScheduleRequest;
use App\Http\Requests\Agenda\ServiceRequest;
use App\Models\Clinic;
use App\Models\Schedule;
use App\Models\ScheduleBlock;
use App\Models\Service;
use App\Support\ClinicTime;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Services, weekly schedules and schedule blocks.
 */
class AgendaSettingsController extends Controller
{
    // ---- Services -----------------------------------------------------------

    public function services(Request $request): JsonResponse
    {
        $services = Service::query()
            ->when(! $request->boolean('include_inactive'), fn ($q) => $q->where('is_active', true))
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $services->map(fn (Service $s) => $this->serviceData($s))]);
    }

    public function storeService(ServiceRequest $request): JsonResponse
    {
        return response()->json(['data' => $this->serviceData(Service::create($request->validated()))], Response::HTTP_CREATED);
    }

    public function updateService(ServiceRequest $request, Service $service): JsonResponse
    {
        $service->update($request->validated());

        return response()->json(['data' => $this->serviceData($service)]);
    }

    // ---- Weekly schedules ---------------------------------------------------

    public function schedules(Request $request): JsonResponse
    {
        $request->validate(['branch_id' => ['required', 'uuid']], [], trans('fields'));

        $rows = Schedule::where('branch_id', $request->input('branch_id'))
            ->orderBy('user_id')->orderBy('weekday')->orderBy('start_time')
            ->get(['id', 'branch_id', 'user_id', 'weekday', 'start_time', 'end_time']);

        return response()->json(['data' => $rows->map(fn (Schedule $s) => [
            'id' => $s->id,
            'user_id' => $s->user_id,
            'weekday' => $s->weekday,
            'start_time' => substr($s->start_time, 0, 5),
            'end_time' => substr($s->end_time, 0, 5),
        ])]);
    }

    /**
     * Replaces the week of a branch (user_id null) or of one professional.
     */
    public function replaceSchedule(ScheduleRequest $request): JsonResponse
    {
        $data = $request->validated();
        $userId = $data['user_id'] ?? null;

        DB::transaction(function () use ($data, $userId) {
            Schedule::where('branch_id', $data['branch_id'])
                ->when($userId, fn ($q) => $q->where('user_id', $userId), fn ($q) => $q->whereNull('user_id'))
                ->delete();

            foreach ($data['ranges'] as $range) {
                Schedule::create(['branch_id' => $data['branch_id'], 'user_id' => $userId] + $range);
            }
        });

        return $this->schedules($request->merge(['branch_id' => $data['branch_id']]));
    }

    // ---- Blocks --------------------------------------------------------------

    public function blocks(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
        ], [], trans('fields'));

        $clinic = $this->clinic($request);
        $blocks = ScheduleBlock::query()
            ->overlapping(
                Carbon::parse($filters['from'], $clinic->timezone)->startOfDay()->utc(),
                Carbon::parse($filters['to'], $clinic->timezone)->endOfDay()->utc(),
            )
            ->orderBy('starts_at')
            ->get();

        return response()->json(['data' => $blocks->map(fn (ScheduleBlock $b) => $this->blockData($b, $clinic))]);
    }

    public function storeBlock(ScheduleBlockRequest $request): JsonResponse
    {
        $clinic = $this->clinic($request);
        $data = $request->validated();

        $block = ScheduleBlock::create([
            ...$data,
            'starts_at' => ClinicTime::parse($data['starts_at'], $clinic),
            'ends_at' => ClinicTime::parse($data['ends_at'], $clinic),
            'created_by' => $request->user()->id,
        ]);

        return response()->json(['data' => $this->blockData($block, $clinic)], Response::HTTP_CREATED);
    }

    public function destroyBlock(ScheduleBlock $block): Response
    {
        $block->delete();

        return response()->noContent();
    }

    // ---- Helpers --------------------------------------------------------------

    /**
     * @return array<string, mixed>
     */
    private function serviceData(Service $service): array
    {
        return [
            'id' => $service->id,
            'name' => $service->name,
            'category' => ['value' => $service->category, 'label' => Service::CATEGORIES[$service->category] ?? $service->category],
            'duration_minutes' => $service->duration_minutes,
            'price' => (float) $service->price,
            'tax_rate' => (float) $service->tax_rate,
            'is_bookable_online' => $service->is_bookable_online,
            'color' => $service->color,
            'is_active' => $service->is_active,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function blockData(ScheduleBlock $block, Clinic $clinic): array
    {
        return [
            'id' => $block->id,
            'branch_id' => $block->branch_id,
            'user_id' => $block->user_id,
            'starts_at' => ClinicTime::iso($block->starts_at, $clinic),
            'ends_at' => ClinicTime::iso($block->ends_at, $clinic),
            'reason' => $block->reason,
        ];
    }

    private function clinic(Request $request): Clinic
    {
        return $request->attributes->get('clinic');
    }
}
