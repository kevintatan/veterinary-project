<?php

namespace App\Actions\Agenda;

use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\ClinicMembership;
use App\Models\Schedule;
use App\Models\ScheduleBlock;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Free slots of a day per professional:
 * working hours (own schedule, else the branch's) minus appointments and blocks.
 * Reused later by online booking and the WhatsApp bot.
 */
final class AvailableSlots
{
    /**
     * @return list<array{user: array{id: string, name: string}, slots: list<array{starts_at: Carbon, ends_at: Carbon}>}>
     */
    public function __invoke(Clinic $clinic, string $branchId, string $date, int $durationMinutes, ?string $userId = null): array
    {
        $dayStart = Carbon::parse($date, $clinic->timezone)->startOfDay();
        $dayEnd = $dayStart->copy()->addDay();
        $notBefore = now();

        return $this->professionals($branchId, $userId)
            ->map(function (User $user) use ($branchId, $dayStart, $dayEnd, $durationMinutes, $notBefore) {
                $busy = $this->busyIntervals($branchId, $user->id, $dayStart->copy()->utc(), $dayEnd->copy()->utc());
                $slots = [];

                foreach ($this->workingWindows($branchId, $user->id, $dayStart) as [$from, $to]) {
                    for ($start = $from->copy(); $start->copy()->addMinutes($durationMinutes)->lte($to); $start->addMinutes($durationMinutes)) {
                        $end = $start->copy()->addMinutes($durationMinutes);

                        if ($start->lt($notBefore) || $this->overlapsAny($start, $end, $busy)) {
                            continue;
                        }

                        $slots[] = ['starts_at' => $start->copy()->utc(), 'ends_at' => $end->utc()];
                    }
                }

                return ['user' => ['id' => $user->id, 'name' => $user->name], 'slots' => $slots];
            })
            ->values()
            ->all();
    }

    /**
     * Active members shown in the agenda and assigned to the branch.
     *
     * @return Collection<int, User>
     */
    private function professionals(string $branchId, ?string $userId): Collection
    {
        $memberIds = ClinicMembership::query()
            ->where('status', ClinicMembership::STATUS_ACTIVE)
            ->where('shows_in_agenda', true)
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->pluck('user_id');

        return User::query()
            ->whereIn('id', $memberIds)
            ->whereHas('branches', fn ($q) => $q->where('branches.id', $branchId))
            ->orderBy('name')
            ->get();
    }

    /**
     * @return list<array{0: Carbon, 1: Carbon}> in clinic timezone
     */
    private function workingWindows(string $branchId, string $userId, Carbon $day): array
    {
        $weekday = $day->isoWeekday();
        $base = Schedule::query()->where('branch_id', $branchId)->where('weekday', $weekday);

        $own = (clone $base)->where('user_id', $userId)->orderBy('start_time')->get();
        $rows = $own->isNotEmpty() ? $own : (clone $base)->whereNull('user_id')->orderBy('start_time')->get();

        return $rows->map(fn (Schedule $s) => [
            $day->copy()->setTimeFromTimeString($s->start_time),
            $day->copy()->setTimeFromTimeString($s->end_time),
        ])->all();
    }

    /**
     * @return list<array{0: Carbon, 1: Carbon}> in UTC
     */
    private function busyIntervals(string $branchId, string $userId, Carbon $from, Carbon $to): array
    {
        $appointments = Appointment::query()
            ->blockingTime()
            ->overlapping($from, $to)
            ->whereHas('users', fn ($q) => $q->where('users.id', $userId))
            ->get(['starts_at', 'ends_at'])
            ->map(fn (Appointment $a) => [$a->starts_at, $a->ends_at]);

        $blocks = ScheduleBlock::query()
            ->overlapping($from, $to)
            ->affecting($branchId, $userId)
            ->get(['starts_at', 'ends_at'])
            ->map(fn (ScheduleBlock $b) => [$b->starts_at, $b->ends_at]);

        return $appointments->concat($blocks)->all();
    }

    /**
     * @param  list<array{0: Carbon, 1: Carbon}>  $intervals
     */
    private function overlapsAny(Carbon $start, Carbon $end, array $intervals): bool
    {
        foreach ($intervals as [$from, $to]) {
            if ($start->lt($to) && $end->gt($from)) {
                return true;
            }
        }

        return false;
    }
}
