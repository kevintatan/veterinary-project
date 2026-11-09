"use client";

import { Card } from "@/components/common/Card";
import { useCan, useSession } from "@/components/providers/SessionProvider";
import Button from "@/components/ui/button/Button";
import {
  addDays,
  addMonths,
  dayOf,
  type Day,
  isValidDay,
  isoWeekday,
  longDayLabel,
  minutesOf,
  minutesOfTime,
  monthGridDays,
  monthLabel,
  shortDayLabel,
  weekDays,
  weekLabel,
} from "@/lib/agenda";
import { useBookingRequestCount } from "@/lib/booking";
import { todayIn } from "@/lib/dates";
import Link from "next/link";
import { api } from "@/lib/api";
import type { Appointment, PetDetail, Professional, ScheduleBlock, ScheduleRange } from "@/lib/types";
import { useQuery } from "@tanstack/react-query";
import { cn } from "@/utils";
import { usePathname, useRouter, useSearchParams } from "next/navigation";
import { useEffect, useMemo, useState } from "react";
import AppointmentDetailPanel from "./AppointmentDetailPanel";
import AppointmentFormModal, { type AppointmentDraft } from "./AppointmentFormModal";
import MiniCalendar from "./MiniCalendar";
import MonthGrid from "./MonthGrid";
import { useAppointments, useBlocks, useProfessionals, useSchedules } from "./queries";
import TimeGrid, { type GridColumn } from "./TimeGrid";

type View = "dia" | "semana" | "mes";

const VIEWS: [View, string][] = [
  ["dia", "Día"],
  ["semana", "Semana"],
  ["mes", "Mes"],
];

const DEFAULT_START = 7 * 60;
const DEFAULT_END = 19 * 60;

export default function AgendaView() {
  const { clinic, branches, user } = useSession();
  const canManage = useCan("appointments.manage");
  const requestCount = useBookingRequestCount(true);
  const router = useRouter();
  const pathname = usePathname();
  const params = useSearchParams();

  const today = todayIn(clinic.timezone);
  const day: Day = isValidDay(params.get("fecha")) ? (params.get("fecha") as Day) : today;
  const view: View = VIEWS.some(([v]) => v === params.get("vista")) ? (params.get("vista") as View) : "dia";
  const branchId = branches.some((b) => b.id === params.get("sede")) ? (params.get("sede") as string) : (branches[0]?.id ?? "");
  const vetFilter = params.get("profesional") ?? "";

  const setParams = (changes: Record<string, string | null>) => {
    const next = new URLSearchParams(params.toString());
    Object.entries(changes).forEach(([k, v]) => (v === null || v === "" ? next.delete(k) : next.set(k, v)));
    router.replace(`${pathname}?${next.toString()}`, { scroll: false });
  };

  const days = view === "dia" ? [day] : view === "semana" ? weekDays(day) : monthGridDays(day);
  const [from, to] = [days[0], days[days.length - 1]];

  const appointments = useAppointments(branchId, from, to);
  const professionals = useProfessionals(branchId);
  const schedules = useSchedules(branchId);
  const blocks = useBlocks(from, to);
  const step = (n: number) => (view === "dia" ? addDays(day, n) : view === "semana" ? addDays(day, 7 * n) : addMonths(day, n));
  const title = view === "dia" ? longDayLabel(day) : view === "semana" ? weekLabel(day) : monthLabel(day);

  const [draft, setDraft] = useState<AppointmentDraft | null>(null);

  // "Agendar cita" from a pet record: /app/agenda?nueva=<petId> opens the form with that pet.
  const presetPetId = params.get("nueva");
  const presetPet = useQuery({
    queryKey: ["pets", presetPetId],
    queryFn: () => api<{ data: PetDetail }>(`pets/${presetPetId}`).then((r) => r.data),
    enabled: !!presetPetId && canManage,
  });
  const [handledPreset, setHandledPreset] = useState<string | null>(null);
  if (presetPet.data && presetPet.data.owner && handledPreset !== presetPet.data.id) {
    setHandledPreset(presetPet.data.id);
    setDraft({ day, minutes: null, userId: vetFilter || null, preset: { owner: presetPet.data.owner, petId: presetPet.data.id } });
  }
  const [selected, setSelected] = useState<Appointment | null>(null);

  // "N" = new appointment (when no dialog is open and not typing).
  useEffect(() => {
    const onKey = (e: KeyboardEvent) => {
      const typing = e.target instanceof HTMLElement && ["INPUT", "TEXTAREA", "SELECT"].includes(e.target.tagName);
      if (e.key.toLowerCase() === "n" && !typing && !e.metaKey && !e.ctrlKey && !draft && !selected && canManage) {
        e.preventDefault();
        setDraft({ day: day === today ? today : day, minutes: null, userId: vetFilter || null });
      }
    };
    window.addEventListener("keydown", onKey);
    return () => window.removeEventListener("keydown", onKey);
  }, [canManage, day, draft, selected, today, vetFilter]);

  const now = useNow(clinic.timezone);
  const pros = professionals.data ?? [];

  const { columns, startMin, endMin } = useMemo(
    () => buildColumns({ view: view === "mes" ? "semana" : view, days: view === "mes" ? [] : days, today, now, pros, vetFilter, list: appointments.data ?? [], schedules: schedules.data ?? [], blocks: blocks.data ?? [], branchId }),
    // eslint-disable-next-line react-hooks/exhaustive-deps
    [view, from, to, today, now, pros, vetFilter, appointments.data, schedules.data, blocks.data, branchId],
  );

  return (
    <>
      {/* Toolbar */}
      <div className="mb-4 flex flex-wrap items-center gap-3">
        <div>
          <h1 className="text-theme-xl font-semibold text-gray-800 dark:text-white/90">Agenda</h1>
          <MiniCalendar value={day} today={today} branchId={branchId} label={title} onChange={(d) => setParams({ fecha: d === today ? null : d })} />
        </div>

        <div className="ms-auto flex flex-wrap items-center gap-2">
          {branches.length > 1 && (
            <select
              aria-label="Sede"
              value={branchId}
              onChange={(e) => setParams({ sede: e.target.value })}
              className="h-10 rounded-lg border border-gray-200 bg-white px-3 text-theme-sm text-gray-700 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300"
            >
              {branches.map((b) => (
                <option key={b.id} value={b.id}>
                  {b.name}
                </option>
              ))}
            </select>
          )}

          {view !== "dia" && (
            <select
              aria-label="Profesional"
              value={vetFilter}
              onChange={(e) => setParams({ profesional: e.target.value })}
              className="h-10 rounded-lg border border-gray-200 bg-white px-3 text-theme-sm text-gray-700 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300"
            >
              <option value="">Todos los profesionales</option>
              {pros.map((p) => (
                <option key={p.id} value={p.id}>
                  {p.name}
                </option>
              ))}
            </select>
          )}

          <div className="inline-flex rounded-lg border border-gray-200 dark:border-gray-800">
            <NavButton label="Anterior" onClick={() => setParams({ fecha: step(-1) })}>‹</NavButton>
            <button
              type="button"
              onClick={() => setParams({ fecha: null })}
              className="border-x border-gray-200 px-3 text-theme-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-800 dark:text-gray-300 dark:hover:bg-white/5"
            >
              Hoy
            </button>
            <NavButton label="Siguiente" onClick={() => setParams({ fecha: step(1) })}>›</NavButton>
          </div>

          <div role="group" aria-label="Vista" className="inline-flex rounded-lg bg-gray-100 p-0.5 dark:bg-gray-900">
            {VIEWS.map(([v, text]) => (
              <button
                key={v}
                type="button"
                aria-pressed={view === v}
                onClick={() => setParams({ vista: v === "dia" ? null : v })}
                className={cn(
                  "rounded-md px-3 py-2 text-theme-sm font-medium",
                  view === v ? "bg-white text-gray-900 shadow-theme-xs dark:bg-gray-800 dark:text-white" : "text-gray-500 hover:text-gray-900 dark:text-gray-400",
                )}
              >
                {text}
              </button>
            ))}
          </div>

          {(requestCount.data ?? 0) > 0 && (
            <Link
              href="/app/agenda/solicitudes"
              className="inline-flex items-center gap-2 rounded-lg border border-dashed border-[#7a5af8] bg-[#f4f3ff] px-3 py-2 text-theme-sm font-medium text-[#5925dc] hover:bg-[#ebe9fe] dark:bg-[#7a5af8]/10 dark:text-[#bdb4fe]"
            >
              Solicitudes en línea
              <span className="rounded-full bg-[#6938ef] px-1.5 text-theme-xs font-semibold text-white">{requestCount.data}</span>
            </Link>
          )}

          {canManage && (
            <Button size="sm" onClick={() => setDraft({ day, minutes: null, userId: vetFilter || null })} title="Nueva cita (N)">
              Nueva cita
            </Button>
          )}
        </div>
      </div>

      <Card className={cn("overflow-hidden transition-opacity", appointments.isFetching && !appointments.isLoading && "opacity-80")}>
        {appointments.isError ? (
          <p className="p-6 text-theme-sm text-error-600">No fue posible cargar la agenda. Intenta de nuevo.</p>
        ) : view === "dia" && !professionals.isLoading && pros.length === 0 ? (
          <p className="p-6 text-theme-sm text-gray-500">
            Nadie de esta sede aparece en la agenda. Asigna profesionales a la sede en Configuración → Equipo.
          </p>
        ) : view === "mes" ? (
          <MonthGrid
            day={day}
            today={today}
            appointments={(appointments.data ?? []).filter((a) => !vetFilter || a.users.some((u) => u.id === vetFilter))}
            closedWeekdays={closedWeekdays(schedules.data ?? [])}
            onDay={(d) => setParams({ fecha: d === today ? null : d, vista: null })}
            onAppointment={setSelected}
          />
        ) : (
          <TimeGrid
            columns={columns}
            startMin={startMin}
            endMin={endMin}
            onAppointment={setSelected}
            onSlot={(column, minutes) =>
              canManage &&
              setDraft({
                day: (column.key.startsWith("day:") ? column.key.slice(4) : day) as Day,
                minutes,
                userId: column.key.startsWith("pro:") ? column.key.slice(4) : vetFilter || null,
              })
            }
            slotLabel={(column, minutes) =>
              `Agendar ${column.key.startsWith("pro:") ? `con ${column.title}` : column.title} a las ${Math.floor(minutes / 60)}:${String(minutes % 60).padStart(2, "0")}`
            }
          />
        )}
      </Card>

      {draft && (
        <AppointmentFormModal
          key={JSON.stringify(draft)}
          draft={draft}
          branchId={branchId}
          professionals={pros}
          defaultUserId={draft.userId ?? (pros.some((p) => p.id === user.id) ? user.id : (pros[0]?.id ?? null))}
          onClose={() => {
            setDraft(null);
            if (presetPetId) setParams({ nueva: null });
          }}
        />
      )}

      {selected && (
        <AppointmentDetailPanel
          key={selected.id}
          appointmentId={selected.id}
          canManage={canManage}
          onClose={() => setSelected(null)}
          onReschedule={(appointment) => {
            setSelected(null);
            setDraft({
              day: dayOf(appointment.starts_at),
              minutes: appointment.is_time_unset ? null : minutesOf(appointment.starts_at),
              userId: appointment.users[0]?.id ?? null,
              appointment,
            });
          }}
        />
      )}
    </>
  );
}

function NavButton({ label, onClick, children }: { label: string; onClick: () => void; children: React.ReactNode }) {
  return (
    <button type="button" aria-label={label} onClick={onClick} className="px-3 py-2 text-lg leading-none text-gray-600 hover:bg-gray-50 dark:text-gray-400 dark:hover:bg-white/5">
      {children}
    </button>
  );
}

/** Minutes since midnight now, in the clinic timezone; refreshed every minute. */
function useNow(timeZone: string): number {
  const read = () => {
    const parts = new Intl.DateTimeFormat("en-GB", { timeZone, hour: "2-digit", minute: "2-digit", hourCycle: "h23" }).formatToParts(new Date());
    return Number(parts.find((p) => p.type === "hour")?.value) * 60 + Number(parts.find((p) => p.type === "minute")?.value);
  };
  const [now, setNow] = useState(read);

  useEffect(() => {
    const id = setInterval(() => setNow(read()), 60_000);
    return () => clearInterval(id);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [timeZone]);

  return now;
}

/** Columns for the day view (one per professional) or the week view (one per day). */
function buildColumns({
  view,
  days,
  today,
  now,
  pros,
  vetFilter,
  list,
  schedules,
  blocks,
  branchId,
}: {
  view: View;
  days: Day[];
  today: Day;
  now: number;
  pros: Professional[];
  vetFilter: string;
  list: Appointment[];
  schedules: ScheduleRange[];
  blocks: ScheduleBlock[];
  branchId: string;
}): { columns: GridColumn[]; startMin: number; endMin: number } {
  const hoursFor = (weekday: number, userId: string | null): [number, number][] => {
    const own = userId ? schedules.filter((s) => s.user_id === userId && s.weekday === weekday) : [];
    const rows = own.length > 0 ? own : schedules.filter((s) => s.user_id === null && s.weekday === weekday);
    return rows.map((s) => [minutesOfTime(s.start_time), minutesOfTime(s.end_time)]);
  };

  const blocksFor = (day: Day, userId: string | null) =>
    blocks
      .filter((b) => (b.branch_id === null || b.branch_id === branchId) && (b.user_id === null || b.user_id === userId))
      .filter((b) => dayOf(b.starts_at) <= day && dayOf(b.ends_at) >= day)
      .map((b) => ({
        start: dayOf(b.starts_at) < day ? 0 : minutesOf(b.starts_at),
        end: dayOf(b.ends_at) > day ? 24 * 60 : minutesOf(b.ends_at),
        reason: b.reason,
      }));

  const columns: GridColumn[] =
    view === "dia"
      ? pros.map((p) => ({
          key: `pro:${p.id}`,
          title: p.name,
          openRanges: hoursFor(isoWeekday(days[0]), p.id),
          blocks: blocksFor(days[0], p.id),
          appointments: list.filter((a) => a.users.some((u) => u.id === p.id)),
          nowMinutes: days[0] === today ? now : undefined,
        }))
      : days.map((d) => ({
          key: `day:${d}`,
          title: shortDayLabel(d),
          highlight: d === today,
          openRanges: hoursFor(isoWeekday(d), vetFilter || null),
          blocks: blocksFor(d, vetFilter || null),
          appointments: list.filter((a) => dayOf(a.starts_at) === d && (!vetFilter || a.users.some((u) => u.id === vetFilter))),
          nowMinutes: d === today ? now : undefined,
        }));

  // Unassigned appointments still need a home in the day view.
  if (view === "dia") {
    const unassigned = list.filter((a) => a.users.length === 0 || !a.users.some((u) => pros.some((p) => p.id === u.id)));
    if (unassigned.length > 0) {
      columns.push({ key: "pro:none", title: "Sin asignar", openRanges: [], blocks: [], appointments: unassigned });
    }
  }

  // Visible range: working hours and appointments, at least 7:00–19:00, in whole hours.
  const edges = [
    ...schedules.flatMap((s) => [minutesOfTime(s.start_time), minutesOfTime(s.end_time)]),
    ...list.filter((a) => !a.is_time_unset).flatMap((a) => [minutesOf(a.starts_at), minutesOf(a.ends_at) || 24 * 60]),
  ];
  const startMin = Math.floor(Math.min(DEFAULT_START, ...edges) / 60) * 60;
  const endMin = Math.ceil(Math.max(DEFAULT_END, ...edges) / 60) * 60;

  return { columns, startMin, endMin };
}

/** Weekdays with no clinic-wide working hours (e.g. Sunday): shaded in the month view. */
function closedWeekdays(schedules: ScheduleRange[]): Set<number> {
  const open = new Set(schedules.filter((s) => s.user_id === null).map((s) => s.weekday));
  return new Set([1, 2, 3, 4, 5, 6, 7].filter((d) => open.size > 0 && !open.has(d)));
}
