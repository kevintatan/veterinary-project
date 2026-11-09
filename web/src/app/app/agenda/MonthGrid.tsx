"use client";

import { appointmentTitle, STATUS_DOT } from "@/components/domain/appointmentStatus";
import { type Day, dayOf, formatMinutes, minutesOf, monthGridDays, WEEKDAY_SHORT } from "@/lib/agenda";
import type { Appointment } from "@/lib/types";
import { cn } from "@/utils";

const VISIBLE = 3;

/**
 * Month view: per day the count of active appointments and the first few.
 * Cancelled ones are left out of the count (they don't occupy the agenda).
 */
export default function MonthGrid({
  day,
  today,
  appointments,
  closedWeekdays,
  onDay,
  onAppointment,
}: {
  day: Day;
  today: Day;
  appointments: Appointment[];
  closedWeekdays: Set<number>;
  onDay: (day: Day) => void;
  onAppointment: (appointment: Appointment) => void;
}) {
  const days = monthGridDays(day);
  const month = day.slice(0, 7);
  const byDay = new Map<Day, Appointment[]>();

  for (const a of appointments) {
    const list = byDay.get(dayOf(a.starts_at)) ?? [];
    list.push(a);
    byDay.set(dayOf(a.starts_at), list);
  }

  byDay.forEach((list) => list.sort((a, b) => Number(a.is_time_unset) - Number(b.is_time_unset) || minutesOf(a.starts_at) - minutesOf(b.starts_at)));

  return (
    <div className="overflow-x-auto">
      <div className="min-w-[720px]">
        <div className="grid grid-cols-7 border-b border-gray-200 dark:border-gray-800">
          {WEEKDAY_SHORT.map((name) => (
            <div key={name} className="px-3 py-2.5 text-theme-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">
              {name}
            </div>
          ))}
        </div>

        <div className="grid grid-cols-7">
          {days.map((d, i) => {
            const list = byDay.get(d) ?? [];
            const active = list.filter((a) => a.status.value !== "cancelled");
            const inMonth = d.slice(0, 7) === month;
            const isToday = d === today;
            const closed = closedWeekdays.has(i % 7 + 1);

            return (
              <div
                key={d}
                className={cn(
                  "group relative min-h-28 border-b border-l border-gray-100 p-1.5 first:border-l-0 dark:border-gray-800 [&:nth-child(7n+1)]:border-l-0",
                  !inMonth && "bg-gray-50/70 dark:bg-white/[0.015]",
                  inMonth && closed && "bg-gray-50 dark:bg-white/[0.02]",
                )}
              >
                <button
                  type="button"
                  onClick={() => onDay(d)}
                  aria-label={`Ver el ${Number(d.slice(8))}`}
                  className="absolute inset-0 z-0 hover:bg-brand-50/40 focus-visible:bg-brand-50 focus-visible:outline-none dark:hover:bg-brand-500/5"
                />
                <div className="relative z-1 mb-1 flex items-center justify-between px-1 pointer-events-none">
                  <span
                    className={cn(
                      "inline-flex h-7 min-w-7 items-center justify-center rounded-full text-theme-sm tabular",
                      isToday ? "bg-brand-500 font-semibold text-white" : inMonth ? "font-medium text-gray-800 dark:text-white/90" : "text-gray-400 dark:text-gray-600",
                    )}
                  >
                    {Number(d.slice(8))}
                  </span>
                  {active.length > 0 && (
                    <span className="text-theme-xs font-medium text-gray-500 tabular dark:text-gray-400">
                      {active.length} {active.length === 1 ? "cita" : "citas"}
                    </span>
                  )}
                </div>

                <ul className="relative z-1 space-y-0.5">
                  {list.slice(0, VISIBLE).map((a) => (
                    <li key={a.id}>
                      <button
                        type="button"
                        onClick={() => onAppointment(a)}
                        className={cn(
                          "flex w-full items-center gap-1.5 truncate rounded px-1.5 py-0.5 text-left text-theme-xs hover:bg-gray-100 dark:hover:bg-white/5",
                          a.status.value === "cancelled" ? "text-gray-400 line-through" : "text-gray-700 dark:text-gray-300",
                        )}
                      >
                        <span className={cn("h-1.5 w-1.5 shrink-0 rounded-full", STATUS_DOT[a.status.value])} aria-hidden />
                        <span className="shrink-0 tabular text-gray-500">{a.is_time_unset ? "—" : formatMinutes(minutesOf(a.starts_at)).replace(" a. m.", "a").replace(" p. m.", "p")}</span>
                        <span className="truncate">{appointmentTitle(a, "Reservado")}</span>
                      </button>
                    </li>
                  ))}
                </ul>
                {list.length > VISIBLE && (
                  <button
                    type="button"
                    onClick={() => onDay(d)}
                    className="relative z-1 mt-0.5 px-1.5 text-theme-xs font-medium text-brand-600 hover:underline dark:text-brand-400"
                  >
                    +{list.length - VISIBLE} más
                  </button>
                )}
              </div>
            );
          })}
        </div>
      </div>
    </div>
  );
}
