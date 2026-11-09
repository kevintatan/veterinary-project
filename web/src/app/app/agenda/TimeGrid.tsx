"use client";

import { appointmentTitle, STATUS_BLOCK, STATUS_DOT } from "@/components/domain/appointmentStatus";
import { formatMinutes, layoutLanes, minutesOf } from "@/lib/agenda";
import type { Appointment } from "@/lib/types";
import { cn } from "@/utils";
import { useEffect, useRef } from "react";

export const PX_PER_MINUTE = 1.6; // 30 min = 48 px
const SLOT_MINUTES = 30;

export type GridColumn = {
  key: string;
  title: string;
  subtitle?: string;
  highlight?: boolean; // today / current professional
  openRanges: [number, number][]; // working hours, minutes since midnight
  blocks: { start: number; end: number; reason: string }[];
  appointments: Appointment[];
  nowMinutes?: number; // only on today's column
};

/**
 * Day/week agenda grid. Times are minutes since midnight in the clinic timezone.
 */
export default function TimeGrid({
  columns,
  startMin,
  endMin,
  onSlot,
  onAppointment,
  slotLabel,
}: {
  columns: GridColumn[];
  startMin: number;
  endMin: number;
  onSlot: (column: GridColumn, minutes: number) => void;
  onAppointment: (appointment: Appointment) => void;
  slotLabel: (column: GridColumn, minutes: number) => string;
}) {
  const height = (endMin - startMin) * PX_PER_MINUTE;
  const hours = Array.from({ length: Math.floor((endMin - startMin) / 60) + 1 }, (_, i) => startMin + i * 60);
  const slots = Array.from({ length: (endMin - startMin) / SLOT_MINUTES }, (_, i) => startMin + i * SLOT_MINUTES);
  const y = (minutes: number) => (Math.min(Math.max(minutes, startMin), endMin) - startMin) * PX_PER_MINUTE;
  const unset = columns.map((c) => c.appointments.filter((a) => a.is_time_unset));
  const hasUnset = unset.some((list) => list.length > 0);

  // Open scrolled near "now" (or the first appointment), like a paper agenda opened at the right page.
  const scroller = useRef<HTMLDivElement>(null);
  const firstKey = columns.map((c) => c.key).join();
  useEffect(() => {
    const now = columns.find((c) => c.nowMinutes !== undefined)?.nowMinutes;
    const first = Math.min(...columns.flatMap((c) => c.appointments.filter((a) => !a.is_time_unset).map((a) => minutesOf(a.starts_at))));
    const target = now ?? (Number.isFinite(first) ? first : 8 * 60);
    scroller.current?.scrollTo({ top: Math.max(0, (target - startMin - 60) * PX_PER_MINUTE) });
    // Only when the set of columns (day/week/branch) changes, not on every refetch.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [firstKey, startMin]);

  return (
    <div ref={scroller} className="max-h-[calc(100dvh-12.5rem)] min-h-96 overflow-auto">
      <div className="min-w-max" style={{ minWidth: `${64 + columns.length * 180}px` }}>
        {/* Column headers */}
        <div className="sticky top-0 z-20 flex border-b border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
          <div className="w-16 shrink-0" />
          {columns.map((column) => (
            <div
              key={column.key}
              className={cn("min-w-45 flex-1 border-l border-gray-100 px-3 py-3 dark:border-gray-800", column.highlight && "bg-brand-25 dark:bg-brand-500/5")}
            >
              <p className={cn("truncate text-theme-sm font-semibold", column.highlight ? "text-brand-700 dark:text-brand-300" : "text-gray-800 dark:text-white/90")}>
                {column.title}
              </p>
              {column.subtitle && <p className="truncate text-theme-xs text-gray-500">{column.subtitle}</p>}
            </div>
          ))}
        </div>

        {/* Appointments without a set time (order of arrival) */}
        {hasUnset && (
          <div className="flex border-b border-gray-200 dark:border-gray-800">
            <div className="flex w-16 shrink-0 items-center justify-end pe-2 text-theme-xs text-gray-400">Sin hora</div>
            {columns.map((column, i) => (
              <div key={column.key} className="min-w-45 flex-1 space-y-1 border-l border-gray-100 p-1.5 dark:border-gray-800">
                {unset[i].map((a) => (
                  <AppointmentChip key={a.id} appointment={a} onClick={() => onAppointment(a)} />
                ))}
              </div>
            ))}
          </div>
        )}

        {/* Grid body */}
        <div className="relative flex">
          <div className="relative w-16 shrink-0" style={{ height }}>
            {hours.map((h) => (
              <span key={h} className="absolute end-2 -translate-y-1/2 text-theme-xs text-gray-400 tabular" style={{ top: y(h) }}>
                {h === startMin ? "" : formatMinutes(h).replace(":00", "")}
              </span>
            ))}
          </div>

          {columns.map((column) => (
            <div key={column.key} className="relative min-w-45 flex-1 border-l border-gray-100 dark:border-gray-800" style={{ height }}>
              {/* Closed hours (outside working ranges) */}
              {closedRanges(column.openRanges, startMin, endMin).map(([from, to]) => (
                <div key={`c${from}`} className="absolute inset-x-0 bg-gray-50 dark:bg-white/2" style={{ top: y(from), height: y(to) - y(from) }} aria-hidden />
              ))}

              {/* Hour and half-hour lines */}
              {slots.map((s) => (
                <div
                  key={`l${s}`}
                  className={cn("absolute inset-x-0 border-t", (s - startMin) % 60 === 0 ? "border-gray-100 dark:border-gray-800" : "border-gray-50 dark:border-white/3")}
                  style={{ top: y(s) }}
                  aria-hidden
                />
              ))}

              {/* Clickable empty slots */}
              {slots.map((s) => (
                <button
                  key={`s${s}`}
                  type="button"
                  aria-label={slotLabel(column, s)}
                  onClick={() => onSlot(column, s)}
                  className="group absolute inset-x-0 z-0 outline-none hover:bg-brand-50/60 focus-visible:bg-brand-50 dark:hover:bg-brand-500/5"
                  style={{ top: y(s), height: SLOT_MINUTES * PX_PER_MINUTE }}
                >
                  <span className="hidden ps-2 text-theme-xs text-brand-600 group-hover:inline group-focus-visible:inline">+ {formatMinutes(s)}</span>
                </button>
              ))}

              {/* Schedule blocks (vacations, holidays…) */}
              {column.blocks.map((b, i) => (
                <div
                  key={`b${i}`}
                  className="pointer-events-none absolute inset-x-0 z-1 overflow-hidden border-y border-gray-200 px-2 py-1 dark:border-gray-700"
                  style={{
                    top: y(b.start),
                    height: Math.max(y(b.end) - y(b.start), 18),
                    backgroundImage: "repeating-linear-gradient(135deg, var(--color-gray-100) 0 6px, transparent 6px 12px)",
                  }}
                >
                  <span className="rounded bg-white/90 px-1.5 text-theme-xs font-medium text-gray-600 dark:bg-gray-900/90 dark:text-gray-300">Bloqueado · {b.reason}</span>
                </div>
              ))}

              {/* Appointments */}
              {layoutLanes(
                column.appointments
                  .filter((a) => !a.is_time_unset)
                  .map((a) => ({ appointment: a, start: minutesOf(a.starts_at), end: minutesOf(a.ends_at) || 24 * 60 })),
              ).map(({ appointment, start, end, lane, lanes }) => (
                <button
                  key={appointment.id}
                  type="button"
                  onClick={() => onAppointment(appointment)}
                  className={cn(
                    "absolute z-10 overflow-hidden rounded-md border-l-[3px] px-2 py-1 text-left shadow-theme-xs transition hover:z-20 hover:shadow-theme-md focus-visible:z-20 focus-visible:ring-2 focus-visible:ring-brand-500",
                    STATUS_BLOCK[appointment.status.value],
                  )}
                  style={{
                    top: y(start) + 1,
                    height: Math.max(y(end) - y(start) - 2, 22),
                    left: `calc(${(lane / lanes) * 100}% + 2px)`,
                    width: `calc(${100 / lanes}% - 4px)`,
                  }}
                  title={`${formatMinutes(start)} · ${appointmentTitle(appointment)} · ${appointment.status.label}`}
                >
                  <p className="truncate text-theme-xs font-semibold text-gray-800 dark:text-white/90">
                    <span className="tabular">{formatMinutes(start)}</span> · {appointmentTitle(appointment)}
                  </p>
                  {end - start >= 30 && (
                    <p className="truncate text-theme-xs text-gray-600 dark:text-gray-400">
                      {appointment.service?.name ?? "Sin servicio"}
                      {appointment.owner && ` · ${appointment.owner.full_name}`}
                    </p>
                  )}
                </button>
              ))}

              {/* Now line */}
              {column.nowMinutes !== undefined && column.nowMinutes >= startMin && column.nowMinutes <= endMin && (
                <div className="pointer-events-none absolute inset-x-0 z-30 flex items-center" style={{ top: y(column.nowMinutes) }} aria-hidden>
                  <span className="-ms-1 h-2 w-2 rounded-full bg-accent-500" />
                  <span className="h-px flex-1 bg-accent-500" />
                </div>
              )}
            </div>
          ))}
        </div>
      </div>
    </div>
  );
}

function AppointmentChip({ appointment, onClick }: { appointment: Appointment; onClick: () => void }) {
  return (
    <button
      type="button"
      onClick={onClick}
      className="flex w-full items-center gap-1.5 truncate rounded-md bg-gray-50 px-2 py-1 text-left text-theme-xs text-gray-700 hover:bg-gray-100 dark:bg-white/5 dark:text-gray-300"
    >
      <span className={cn("h-1.5 w-1.5 shrink-0 rounded-full", STATUS_DOT[appointment.status.value])} aria-hidden />
      <span className="truncate">{appointmentTitle(appointment)}</span>
    </button>
  );
}

/** Complement of the working ranges inside [start, end]. */
function closedRanges(open: [number, number][], start: number, end: number): [number, number][] {
  const sorted = [...open].sort((a, b) => a[0] - b[0]);
  const closed: [number, number][] = [];
  let cursor = start;

  for (const [from, to] of sorted) {
    if (from > cursor) closed.push([cursor, Math.min(from, end)]);
    cursor = Math.max(cursor, to);
  }

  if (cursor < end) closed.push([cursor, end]);

  return closed.filter(([a, b]) => b > a);
}
