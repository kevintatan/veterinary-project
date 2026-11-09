"use client";

import { addDays, addMonths, type Day, dayOf, monthGridDays, monthLabel, WEEKDAY_SHORT } from "@/lib/agenda";
import { cn } from "@/utils";
import { useEffect, useRef, useState } from "react";
import { useAppointments } from "./queries";

/**
 * Date title that opens a month picker. Days with appointments carry a dot,
 * so "when is the next free day?" is answered at a glance.
 */
export default function MiniCalendar({
  value,
  today,
  branchId,
  label,
  onChange,
}: {
  value: Day;
  today: Day;
  branchId: string;
  label: string;
  onChange: (day: Day) => void;
}) {
  const [open, setOpen] = useState(false);
  const [cursor, setCursor] = useState(value);
  const ref = useRef<HTMLDivElement>(null);

  useEffect(() => {
    if (!open) return;
    const onDown = (e: MouseEvent) => ref.current && !ref.current.contains(e.target as Node) && setOpen(false);
    const onKey = (e: KeyboardEvent) => e.key === "Escape" && setOpen(false);
    document.addEventListener("mousedown", onDown);
    document.addEventListener("keydown", onKey);
    return () => {
      document.removeEventListener("mousedown", onDown);
      document.removeEventListener("keydown", onKey);
    };
  }, [open]);

  const days = monthGridDays(cursor);
  const appointments = useAppointments(open ? branchId : "", days[0], days[41]);
  const busy = new Set((appointments.data ?? []).filter((a) => a.status.value !== "cancelled").map((a) => dayOf(a.starts_at)));
  const month = cursor.slice(0, 7);

  return (
    <div ref={ref} className="relative">
      <button
        type="button"
        aria-expanded={open}
        onClick={() => {
          setCursor(value);
          setOpen((v) => !v);
        }}
        className="group -mx-1.5 flex items-center gap-1 rounded-md px-1.5 py-0.5 text-theme-sm text-gray-500 first-letter:uppercase hover:bg-gray-100 hover:text-gray-800 dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-gray-200"
      >
        <span className="first-letter:uppercase">{label}</span>
        <svg width="14" height="14" viewBox="0 0 20 20" fill="none" aria-hidden className={cn("transition", open && "rotate-180")}>
          <path d="M5 7.5 10 12.5 15 7.5" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" />
        </svg>
      </button>

      {open && (
        <div className="absolute top-full left-0 z-40 mt-2 w-72 rounded-xl border border-gray-200 bg-white p-3 shadow-theme-lg dark:border-gray-800 dark:bg-gray-900">
          <div className="mb-2 flex items-center justify-between">
            <button type="button" aria-label="Mes anterior" onClick={() => setCursor(addMonths(cursor, -1))} className="rounded-md p-1.5 text-gray-500 hover:bg-gray-100 dark:hover:bg-white/5">
              ‹
            </button>
            <span className="text-theme-sm font-semibold text-gray-800 first-letter:uppercase dark:text-white/90">{monthLabel(cursor)}</span>
            <button type="button" aria-label="Mes siguiente" onClick={() => setCursor(addMonths(cursor, 1))} className="rounded-md p-1.5 text-gray-500 hover:bg-gray-100 dark:hover:bg-white/5">
              ›
            </button>
          </div>

          <div className="grid grid-cols-7 text-center">
            {WEEKDAY_SHORT.map((d) => (
              <span key={d} className="pb-1 text-[11px] font-medium text-gray-400">
                {d.slice(0, 2)}
              </span>
            ))}
            {days.map((d) => {
              const selected = d === value;
              const inMonth = d.slice(0, 7) === month;
              return (
                <button
                  key={d}
                  type="button"
                  onClick={() => {
                    onChange(d);
                    setOpen(false);
                  }}
                  className={cn(
                    "relative mx-auto flex h-8 w-8 items-center justify-center rounded-full text-theme-xs tabular",
                    selected
                      ? "bg-brand-500 font-semibold text-white"
                      : d === today
                        ? "font-semibold text-brand-600 ring-1 ring-brand-500/40 dark:text-brand-400"
                        : inMonth
                          ? "text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-white/5"
                          : "text-gray-300 hover:bg-gray-50 dark:text-gray-600",
                  )}
                >
                  {Number(d.slice(8))}
                  {busy.has(d) && !selected && <span className="absolute bottom-0.5 h-1 w-1 rounded-full bg-brand-500" aria-hidden />}
                </button>
              );
            })}
          </div>

          <div className="mt-2 flex gap-1 border-t border-gray-100 pt-2 dark:border-gray-800">
            {(
              [
                ["Hoy", today],
                ["En 1 semana", addDays(today, 7)],
                ["En 1 mes", addMonths(today, 1)],
              ] as const
            ).map(([text, target]) => (
              <button
                key={text}
                type="button"
                onClick={() => {
                  onChange(target);
                  setOpen(false);
                }}
                className="flex-1 rounded-md px-2 py-1.5 text-theme-xs font-medium text-gray-600 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-white/5"
              >
                {text}
              </button>
            ))}
          </div>
        </div>
      )}
    </div>
  );
}

