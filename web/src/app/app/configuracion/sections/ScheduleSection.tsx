"use client";

import { Card, CardHeader, Skeleton } from "@/components/common/Card";
import { Select } from "@/components/form/fields";
import Button from "@/components/ui/button/Button";
import { api, ApiError } from "@/lib/api";
import { type Branch, WEEKDAYS } from "@/lib/settings";
import type { Professional, ScheduleRange } from "@/lib/types";
import { cn } from "@/utils";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useState } from "react";

type Ranges = Record<number, { start: string; end: string }[]>;

const toRanges = (rows: ScheduleRange[]): Ranges =>
  Object.fromEntries(
    WEEKDAYS.map(([d]) => [
      d,
      rows
        .filter((r) => r.weekday === d)
        .sort((a, b) => a.start_time.localeCompare(b.start_time))
        .map((r) => ({ start: r.start_time.slice(0, 5), end: r.end_time.slice(0, 5) })),
    ]),
  );

/**
 * Opening hours per branch (general) and, optionally, per professional. A
 * professional without own hours follows the branch's.
 */
export default function ScheduleSection() {
  const branches = useQuery({ queryKey: ["branches"], queryFn: () => api<{ data: Branch[] }>("branches").then((r) => r.data.filter((b) => b.is_active)) });
  const [branchId, setBranchId] = useState("");
  const [userId, setUserId] = useState("");
  const activeBranch = branchId || branches.data?.[0]?.id || "";

  const professionals = useQuery({
    queryKey: ["agenda", "professionals", activeBranch],
    queryFn: () => api<{ data: Professional[] }>(`appointments/professionals?branch_id=${activeBranch}`).then((r) => r.data),
    enabled: !!activeBranch,
  });
  const schedules = useQuery({
    queryKey: ["agenda", "schedules", activeBranch],
    queryFn: () => api<{ data: ScheduleRange[] }>(`schedules?branch_id=${activeBranch}`).then((r) => r.data),
    enabled: !!activeBranch,
  });

  if (!branches.data || !schedules.data) return <Skeleton className="h-96" />;

  const general = schedules.data.filter((s) => s.user_id === null);
  const own = userId ? schedules.data.filter((s) => s.user_id === userId) : general;

  return (
    <Card>
      <CardHeader title="Horario de atención" description="Las horas fuera de este horario se ven sombreadas en la agenda." />
      <div className="flex flex-wrap gap-3 border-b border-gray-100 px-5 py-4 dark:border-gray-800">
        {branches.data.length > 1 && (
          <Select aria-label="Sede" className="w-auto" value={activeBranch} onChange={(e) => (setBranchId(e.target.value), setUserId(""))}>
            {branches.data.map((b) => (
              <option key={b.id} value={b.id}>
                {b.name}
              </option>
            ))}
          </Select>
        )}
        <Select aria-label="Horario de" className="w-auto" value={userId} onChange={(e) => setUserId(e.target.value)}>
          <option value="">Horario general de la sede</option>
          {professionals.data?.map((p) => (
            <option key={p.id} value={p.id}>
              {p.name} {schedules.data.some((s) => s.user_id === p.id) ? "· horario propio" : ""}
            </option>
          ))}
        </Select>
      </div>
      <WeekEditor
        key={`${activeBranch}:${userId}:${own.length}`}
        branchId={activeBranch}
        userId={userId || null}
        initial={toRanges(own)}
        followsGeneral={!!userId && own.length === 0}
        general={toRanges(general)}
      />
    </Card>
  );
}

function WeekEditor({ branchId, userId, initial, followsGeneral, general }: { branchId: string; userId: string | null; initial: Ranges; followsGeneral: boolean; general: Ranges }) {
  const queryClient = useQueryClient();
  const [ranges, setRanges] = useState<Ranges>(followsGeneral ? general : initial);
  const [custom, setCustom] = useState(!followsGeneral);
  const dirty = JSON.stringify(ranges) !== JSON.stringify(followsGeneral ? general : initial) || (followsGeneral && custom);

  const save = useMutation({
    mutationFn: (payload: Ranges | null) =>
      api("schedules", {
        method: "PUT",
        body: {
          branch_id: branchId,
          user_id: userId,
          ranges: payload ? Object.entries(payload).flatMap(([d, list]) => list.map((r) => ({ weekday: Number(d), start_time: r.start, end_time: r.end }))) : [],
        },
      }),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ["agenda"] }),
  });
  const error = save.error instanceof ApiError ? (Object.values(save.error.errors)[0]?.[0] ?? save.error.message) : null;
  const setDay = (day: number, list: { start: string; end: string }[]) => setRanges((r) => ({ ...r, [day]: list }));

  if (userId && !custom) {
    return (
      <div className="space-y-4 p-5">
        <p className="text-theme-sm text-gray-600 dark:text-gray-400">Este profesional sigue el horario general de la sede.</p>
        <Summary ranges={general} />
        <Button size="sm" variant="outline" onClick={() => setCustom(true)}>
          Definir un horario propio
        </Button>
      </div>
    );
  }

  return (
    <div className="p-5">
      <ul className="divide-y divide-gray-100 dark:divide-gray-800">
        {WEEKDAYS.map(([day, label]) => {
          const list = ranges[day] ?? [];
          return (
            <li key={day} className="flex flex-wrap items-start gap-4 py-3">
              <div className="flex w-36 items-center gap-3 pt-1.5">
                <button
                  type="button"
                  role="switch"
                  aria-checked={list.length > 0}
                  aria-label={`${label} abierto`}
                  onClick={() => setDay(day, list.length > 0 ? [] : [{ start: "08:00", end: "18:00" }])}
                  className={cn("relative h-5 w-9 shrink-0 rounded-full transition", list.length > 0 ? "bg-brand-500" : "bg-gray-200 dark:bg-gray-700")}
                >
                  <span className={cn("absolute top-0.5 left-0.5 h-4 w-4 rounded-full bg-white transition", list.length > 0 && "translate-x-4")} />
                </button>
                <span className={cn("text-theme-sm font-medium", list.length > 0 ? "text-gray-800 dark:text-white/90" : "text-gray-400")}>{label}</span>
              </div>
              <div className="flex-1 space-y-2">
                {list.length === 0 ? (
                  <p className="pt-1.5 text-theme-sm text-gray-400">Cerrado</p>
                ) : (
                  list.map((r, i) => (
                    <div key={i} className="flex items-center gap-2">
                      <input type="time" aria-label={`${label} desde`} value={r.start} step={900} onChange={(e) => setDay(day, list.map((x, j) => (j === i ? { ...x, start: e.target.value } : x)))} className="h-9 rounded-lg border border-gray-300 bg-transparent px-2 text-theme-sm tabular dark:border-gray-700 dark:text-white/90" />
                      <span className="text-gray-400">a</span>
                      <input type="time" aria-label={`${label} hasta`} value={r.end} step={900} onChange={(e) => setDay(day, list.map((x, j) => (j === i ? { ...x, end: e.target.value } : x)))} className="h-9 rounded-lg border border-gray-300 bg-transparent px-2 text-theme-sm tabular dark:border-gray-700 dark:text-white/90" />
                      {list.length > 1 && (
                        <button type="button" aria-label="Quitar franja" onClick={() => setDay(day, list.filter((_, j) => j !== i))} className="rounded-lg px-2 py-1 text-gray-400 hover:bg-gray-100 hover:text-error-600 dark:hover:bg-white/5">
                          ✕
                        </button>
                      )}
                    </div>
                  ))
                )}
              </div>
              {list.length > 0 && (
                <div className="flex gap-3 pt-1.5 text-theme-xs font-medium">
                  <button type="button" onClick={() => setDay(day, [...list, { start: list[list.length - 1].end, end: "18:00" }])} className="text-brand-600 hover:underline dark:text-brand-400">
                    + Franja
                  </button>
                  {day === 1 && (
                    <button type="button" onClick={() => setRanges((r) => ({ ...r, 2: list, 3: list, 4: list, 5: list }))} className="text-gray-500 hover:text-gray-800 dark:hover:text-gray-200">
                      Copiar a martes–viernes
                    </button>
                  )}
                </div>
              )}
            </li>
          );
        })}
      </ul>
      <p className="mt-2 text-theme-xs text-gray-500">Usa dos franjas para el almuerzo, por ejemplo 8:00–12:00 y 14:00–18:00.</p>
      <div className="mt-4 flex flex-wrap items-center justify-end gap-3">
        {error && <p className="me-auto text-theme-xs text-error-500">{error}</p>}
        {save.isSuccess && !dirty && <span className="text-theme-xs text-success-600">Guardado</span>}
        {userId && (
          <Button size="sm" variant="ghost" loading={save.isPending && save.variables === null} onClick={() => save.mutate(null, { onSuccess: () => setCustom(false) })}>
            Volver al horario de la sede
          </Button>
        )}
        <Button size="sm" disabled={!dirty} loading={save.isPending && save.variables !== null} onClick={() => save.mutate(ranges)}>
          Guardar horario
        </Button>
      </div>
    </div>
  );
}

function Summary({ ranges }: { ranges: Ranges }) {
  return (
    <ul className="grid gap-1 text-theme-sm sm:grid-cols-2">
      {WEEKDAYS.map(([day, label]) => (
        <li key={day} className="flex gap-3">
          <span className="w-24 text-gray-500">{label}</span>
          <span className="text-gray-800 tabular dark:text-white/90">{ranges[day]?.length ? ranges[day].map((r) => `${r.start}–${r.end}`).join(", ") : "Cerrado"}</span>
        </li>
      ))}
    </ul>
  );
}
