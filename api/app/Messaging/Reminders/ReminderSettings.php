<?php

namespace App\Messaging\Reminders;

use App\Models\Clinic;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * A clinic's sending window and minimum notice (clinics.settings.reminders,
 * defaults in config/whatsapp.php). Reminders never go out at night.
 */
final class ReminderSettings
{
    public function __construct(
        public readonly string $sendFrom,     // "08:00" clinic time
        public readonly string $sendUntil,    // "19:00"
        public readonly int $minNoticeHours,  // booked closer than this: no reminder
        public readonly string $timezone,
    ) {}

    public static function for(Clinic $clinic): self
    {
        $values = array_merge(config('whatsapp.defaults'), (array) ($clinic->settings['reminders'] ?? []));

        return new self($values['send_from'], $values['send_until'], (int) $values['min_notice_hours'], $clinic->timezone);
    }

    /** @return array<string, list<mixed>> */
    public static function rules(string $prefix = ''): array
    {
        return [
            "{$prefix}send_from" => ['required', 'date_format:H:i'],
            "{$prefix}send_until" => ['required', 'date_format:H:i', "after:{$prefix}send_from"],
            "{$prefix}min_notice_hours" => ['required', 'integer', 'between:0,48'],
        ];
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['send_from' => $this->sendFrom, 'send_until' => $this->sendUntil, 'min_notice_hours' => $this->minNoticeHours];
    }

    /** Moves an instant into the window of its own local day (earlier → opening, later → closing). */
    public function clamp(CarbonInterface $at): Carbon
    {
        $local = Carbon::instance($at)->setTimezone($this->timezone);
        [$open, $close] = $this->bounds($local);

        return ($local->lt($open) ? $open : ($local->gt($close) ? $close : $local))->utc();
    }

    /** The first sending moment at or after $at (tomorrow's opening if today's window is over). */
    public function next(CarbonInterface $at): Carbon
    {
        $local = Carbon::instance($at)->setTimezone($this->timezone);
        [$open, $close] = $this->bounds($local);

        return match (true) {
            $local->lt($open) => $open->utc(),
            $local->lte($close) => $local->utc(),
            default => $this->bounds($local->copy()->addDay())[0]->utc(),
        };
    }

    public function isOpen(CarbonInterface $at): bool
    {
        $local = Carbon::instance($at)->setTimezone($this->timezone);
        [$open, $close] = $this->bounds($local);

        return $local->betweenIncluded($open, $close);
    }

    /** @return array{Carbon, Carbon} */
    private function bounds(Carbon $local): array
    {
        $day = $local->toDateString();

        return [Carbon::parse("{$day} {$this->sendFrom}", $this->timezone), Carbon::parse("{$day} {$this->sendUntil}", $this->timezone)];
    }
}
