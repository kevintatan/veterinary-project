<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\Pet;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Activity charts for "Hoy en la clínica". Every figure is computed in the
 * clinic timezone (an 8 p.m. appointment belongs to that day, not to the
 * next one in UTC) and compared with the previous period of the same length.
 */
class DashboardController extends Controller
{
    private const RANGES = [7, 30, 90];

    private const TOP_SERVICES = 5;

    public function stats(Request $request): JsonResponse
    {
        $validated = $request->validate(['days' => ['sometimes', 'integer', Rule::in(self::RANGES)]], [], ['days' => 'periodo']);
        $days = (int) ($validated['days'] ?? 30);

        /** @var Clinic $clinic */
        $clinic = $request->attributes->get('clinic');
        $tz = $clinic->timezone;

        $to = Carbon::now($tz)->endOfDay();
        $from = $to->copy()->subDays($days - 1)->startOfDay();
        $previousTo = $from->copy()->subSecond();
        $previousFrom = $previousTo->copy()->subDays($days - 1)->startOfDay();

        $current = $this->totals($from, $to);
        $previous = $this->totals($previousFrom, $previousTo);

        return response()->json(['data' => [
            'range' => ['days' => $days, 'from' => $from->toDateString(), 'to' => $to->toDateString()],
            'totals' => [
                'completed' => $this->metric($current['completed'], $previous['completed']),
                'no_show_rate' => $this->metric($current['no_show_rate'], $previous['no_show_rate']),
                'cancelled' => $this->metric($current['cancelled'], $previous['cancelled']),
                'new_patients' => $this->metric(
                    $this->newPatients($from, $to),
                    $this->newPatients($previousFrom, $previousTo),
                ),
            ],
            'daily' => $this->daily($from, $to, $tz),
            'top_services' => $this->topServices($from, $to),
        ]]);
    }

    /**
     * @return array{completed: int, no_show: int, cancelled: int, no_show_rate: float|null}
     */
    private function totals(Carbon $from, Carbon $to): array
    {
        $row = $this->inRange($from, $to)
            ->selectRaw("count(*) filter (where status = 'completed') as completed")
            ->selectRaw("count(*) filter (where status = 'no_show') as no_show")
            ->selectRaw("count(*) filter (where status = 'cancelled') as cancelled")
            ->toBase()
            ->first();

        $completed = (int) $row->completed;
        $noShow = (int) $row->no_show;
        // No-show rate over appointments that were due to happen (cancellations excluded).
        $due = $completed + $noShow;

        return [
            'completed' => $completed,
            'no_show' => $noShow,
            'cancelled' => (int) $row->cancelled,
            'no_show_rate' => $due === 0 ? null : round($noShow / $due * 100, 1),
        ];
    }

    /**
     * One row per day (zeros included), grouped in the clinic timezone.
     *
     * @return list<array{date: string, completed: int, missed: int, upcoming: int}>
     */
    private function daily(Carbon $from, Carbon $to, string $tz): array
    {
        $rows = $this->inRange($from, $to)
            ->selectRaw('(starts_at AT TIME ZONE ?)::date as day', [$tz])
            ->selectRaw("count(*) filter (where status = 'completed') as completed")
            ->selectRaw("count(*) filter (where status in ('no_show', 'cancelled')) as missed")
            ->selectRaw("count(*) filter (where status not in ('completed', 'no_show', 'cancelled')) as upcoming")
            ->groupBy('day')
            ->toBase()
            ->get()
            ->keyBy(fn ($row) => (string) $row->day);

        $series = [];

        for ($day = $from->copy(); $day->lte($to); $day->addDay()) {
            $row = $rows[$day->toDateString()] ?? null;
            $series[] = [
                'date' => $day->toDateString(),
                'completed' => (int) ($row->completed ?? 0),
                'missed' => (int) ($row->missed ?? 0),
                'upcoming' => (int) ($row->upcoming ?? 0),
            ];
        }

        return $series;
    }

    /**
     * Most booked services (cancelled excluded); the rest folds into "Otros".
     *
     * @return list<array{name: string, total: int}>
     */
    private function topServices(Carbon $from, Carbon $to): array
    {
        $rows = $this->inRange($from, $to)
            ->where('appointments.status', '!=', 'cancelled')
            ->join('services', 'services.id', '=', 'appointments.service_id')
            ->select('services.name', DB::raw('count(*) as total'))
            ->groupBy('services.name')
            ->orderByDesc('total')
            ->orderBy('services.name')
            ->toBase()
            ->get();

        $top = $rows->take(self::TOP_SERVICES)->map(fn ($r) => ['name' => $r->name, 'total' => (int) $r->total])->values();
        $others = (int) $rows->slice(self::TOP_SERVICES)->sum('total');

        return $others > 0 ? [...$top, ['name' => 'Otros', 'total' => $others]] : $top->all();
    }

    private function newPatients(Carbon $from, Carbon $to): int
    {
        return Pet::whereBetween('created_at', [$from->copy()->utc(), $to->copy()->utc()])->count();
    }

    private function inRange(Carbon $from, Carbon $to): Builder
    {
        return Appointment::query()
            ->whereBetween('appointments.starts_at', [$from->copy()->utc(), $to->copy()->utc()]);
    }

    /**
     * @return array{value: int|float|null, previous: int|float|null}
     */
    private function metric(int|float|null $value, int|float|null $previous): array
    {
        return ['value' => $value, 'previous' => $previous];
    }
}
