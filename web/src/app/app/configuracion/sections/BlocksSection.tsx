"use client";

import { Card, CardHeader, EmptyState, Skeleton } from "@/components/common/Card";
import SidePanel from "@/components/common/SidePanel";
import { Checkbox, Field, Input, Select } from "@/components/form/fields";
import { useSession } from "@/components/providers/SessionProvider";
import Button from "@/components/ui/button/Button";
import { addDays, dayOf, formatMinutes, minutesOf } from "@/lib/agenda";
import { api, ApiError } from "@/lib/api";
import { shortDate } from "@/lib/clinical";
import { todayIn } from "@/lib/dates";
import type { Member } from "@/lib/settings";
import type { ScheduleBlock } from "@/lib/types";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useState } from "react";

/** Holidays, vacations, training days: the agenda shows them striped and won't book over them. */
export default function BlocksSection() {
  const { clinic, branches } = useSession();
  const queryClient = useQueryClient();
  const [creating, setCreating] = useState(false);
  const today = todayIn(clinic.timezone);

  const blocks = useQuery({
    queryKey: ["agenda", "blocks", today, "year"],
    queryFn: () => api<{ data: ScheduleBlock[] }>(`schedule-blocks?from=${today}&to=${addDays(today, 365)}`).then((r) => r.data),
  });
  const members = useQuery({ queryKey: ["members"], queryFn: () => api<{ data: Member[] }>("members").then((r) => r.data) });

  const remove = useMutation({
    mutationFn: (id: string) => api(`schedule-blocks/${id}`, { method: "DELETE" }),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ["agenda"] }),
  });

  const scope = (b: ScheduleBlock) => {
    const who = b.user_id ? (members.data?.find((m) => m.user.id === b.user_id)?.user.name ?? "Un profesional") : null;
    const where = b.branch_id ? (branches.find((x) => x.id === b.branch_id)?.name ?? "Una sede") : null;
    return [who, where].filter(Boolean).join(" · ") || "Toda la clínica";
  };

  const when = (b: ScheduleBlock) => {
    const allDay = minutesOf(b.starts_at) === 0 && (minutesOf(b.ends_at) === 0 || minutesOf(b.ends_at) >= 23 * 60 + 59);
    const [from, to] = [dayOf(b.starts_at), dayOf(b.ends_at)];
    if (allDay) {
      const lastDay = minutesOf(b.ends_at) === 0 ? addDays(to, -1) : to;
      return from === lastDay ? `${shortDate(from)} · todo el día` : `${shortDate(from)} al ${shortDate(lastDay)}`;
    }
    return from === to
      ? `${shortDate(from)} · ${formatMinutes(minutesOf(b.starts_at))} a ${formatMinutes(minutesOf(b.ends_at))}`
      : `${shortDate(from)} ${formatMinutes(minutesOf(b.starts_at))} al ${shortDate(to)} ${formatMinutes(minutesOf(b.ends_at))}`;
  };

  return (
    <Card>
      <CardHeader
        title="Bloqueos de agenda"
        description="Festivos, vacaciones, capacitaciones. Próximos 12 meses."
        action={
          <Button size="sm" onClick={() => setCreating(true)}>
            Nuevo bloqueo
          </Button>
        }
      />
      {!blocks.data ? (
        <Skeleton className="m-5 h-24" />
      ) : blocks.data.length === 0 ? (
        <EmptyState title="No hay bloqueos próximos." description="Agrega festivos y vacaciones para que nadie agende en esas fechas." />
      ) : (
        <ul className="divide-y divide-gray-100 dark:divide-gray-800">
          {blocks.data.map((b) => (
            <li key={b.id} className="flex flex-wrap items-center gap-4 px-5 py-3">
              <div className="min-w-0 flex-1">
                <p className="font-medium text-gray-800 dark:text-white/90">{b.reason}</p>
                <p className="text-theme-xs text-gray-500">
                  {when(b)} · {scope(b)}
                </p>
              </div>
              <Button size="sm" variant="ghost" loading={remove.isPending && remove.variables === b.id} onClick={() => remove.mutate(b.id)}>
                Quitar
              </Button>
            </li>
          ))}
        </ul>
      )}
      {creating && <BlockPanel members={members.data ?? []} onClose={() => setCreating(false)} />}
    </Card>
  );
}

function BlockPanel({ members, onClose }: { members: Member[]; onClose: () => void }) {
  const { clinic, branches } = useSession();
  const queryClient = useQueryClient();
  const today = todayIn(clinic.timezone);
  const [form, setForm] = useState({ reason: "", branch_id: "", user_id: "", allDay: true, from: today, to: today, startTime: "08:00", endTime: "12:00" });

  const save = useMutation({
    mutationFn: () =>
      api("schedule-blocks", {
        method: "POST",
        body: {
          reason: form.reason.trim(),
          branch_id: form.branch_id || null,
          user_id: form.user_id || null,
          // All day: from 00:00 of the first day to 00:00 of the day after the last one.
          starts_at: form.allDay ? `${form.from}T00:00` : `${form.from}T${form.startTime}`,
          ends_at: form.allDay ? `${addDays(form.to, 1)}T00:00` : `${form.allDay ? form.to : form.from}T${form.endTime}`,
        },
      }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["agenda"] });
      onClose();
    },
  });
  const error = save.error instanceof ApiError ? (Object.values(save.error.errors)[0]?.[0] ?? save.error.message) : null;
  const valid = form.reason.trim().length > 1 && (form.allDay ? form.to >= form.from : form.endTime > form.startTime);

  return (
    <SidePanel
      title="Nuevo bloqueo"
      description="Durante el bloqueo no se pueden agendar citas."
      onClose={onClose}
      footer={
        <div className="flex items-center justify-between gap-3">
          <p className="min-w-0 text-theme-xs text-error-500">{error}</p>
          <div className="flex shrink-0 gap-3">
            <Button size="sm" variant="outline" onClick={onClose}>
              Cancelar
            </Button>
            <Button size="sm" type="submit" form="block-form" disabled={!valid} loading={save.isPending}>
              Guardar
            </Button>
          </div>
        </div>
      }
    >
      <form
        id="block-form"
        className="space-y-4"
        onSubmit={(e) => {
          e.preventDefault();
          if (valid) save.mutate();
        }}
      >
        <Field label="Motivo" htmlFor="bk-reason">
          <Input id="bk-reason" autoFocus value={form.reason} onChange={(e) => setForm((f) => ({ ...f, reason: e.target.value }))} placeholder="Ej.: Festivo, vacaciones de Andrés, capacitación" maxLength={150} />
        </Field>
        <div className="grid gap-3 sm:grid-cols-2">
          <Field label="Sede" htmlFor="bk-branch">
            <Select id="bk-branch" value={form.branch_id} onChange={(e) => setForm((f) => ({ ...f, branch_id: e.target.value }))}>
              <option value="">Todas las sedes</option>
              {branches.map((b) => (
                <option key={b.id} value={b.id}>
                  {b.name}
                </option>
              ))}
            </Select>
          </Field>
          <Field label="Profesional" htmlFor="bk-user">
            <Select id="bk-user" value={form.user_id} onChange={(e) => setForm((f) => ({ ...f, user_id: e.target.value }))}>
              <option value="">Todo el equipo</option>
              {members
                .filter((m) => m.status === "active" && m.shows_in_agenda)
                .map((m) => (
                  <option key={m.user.id} value={m.user.id}>
                    {m.user.name}
                  </option>
                ))}
            </Select>
          </Field>
        </div>
        <Checkbox checked={form.allDay} onChange={(e) => setForm((f) => ({ ...f, allDay: e.target.checked }))} label="Días completos" />
        {form.allDay ? (
          <div className="grid grid-cols-2 gap-3">
            <Field label="Desde" htmlFor="bk-from">
              <Input id="bk-from" type="date" value={form.from} onChange={(e) => setForm((f) => ({ ...f, from: e.target.value, to: e.target.value > f.to ? e.target.value : f.to }))} />
            </Field>
            <Field label="Hasta (inclusive)" htmlFor="bk-to">
              <Input id="bk-to" type="date" value={form.to} min={form.from} onChange={(e) => setForm((f) => ({ ...f, to: e.target.value }))} />
            </Field>
          </div>
        ) : (
          <div className="grid grid-cols-3 gap-3">
            <Field label="Día" htmlFor="bk-day">
              <Input id="bk-day" type="date" value={form.from} onChange={(e) => setForm((f) => ({ ...f, from: e.target.value }))} />
            </Field>
            <Field label="Desde" htmlFor="bk-start">
              <Input id="bk-start" type="time" step={900} value={form.startTime} onChange={(e) => setForm((f) => ({ ...f, startTime: e.target.value }))} />
            </Field>
            <Field label="Hasta" htmlFor="bk-end">
              <Input id="bk-end" type="time" step={900} value={form.endTime} onChange={(e) => setForm((f) => ({ ...f, endTime: e.target.value }))} />
            </Field>
          </div>
        )}
        <p className="text-theme-xs text-gray-500">Las citas que ya estén agendadas en esas fechas no se cancelan; revísalas en la agenda.</p>
      </form>
    </SidePanel>
  );
}
