"use client";

import { Card, CardHeader, EmptyState, Skeleton } from "@/components/common/Card";
import WhatsAppText from "@/components/domain/WhatsAppText";
import { Select } from "@/components/form/fields";
import { useCan } from "@/components/providers/SessionProvider";
import Button from "@/components/ui/button/Button";
import { api, ApiError } from "@/lib/api";
import { cn } from "@/utils";
import { keepPreviousData, useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import Link from "next/link";
import { useState } from "react";
import { Switch } from "./ClinicSection";

type Rule = { id: string; trigger: "appointment" | "next_control"; record_type: string | null; offset_minutes: number; is_active: boolean; offsets: number[] };
type Settings = {
  driver: "fake" | "meta";
  plan: { name: string | null; automatic: boolean; monthly_limit: number | null };
  used_this_month: number;
  window: { send_from: string; send_until: string; min_notice_hours: number };
  rules: Rule[];
  counts: Record<string, number>;
};
type ReminderItem = {
  id: string;
  purpose: "appointment_reminder" | "next_control";
  status: { value: string; label: string };
  scheduled_for: string;
  event_at: string;
  attempts: number;
  last_error: string | null;
  pet: { id: string; name: string } | null;
  owner: { id: string; full_name: string } | null;
  message: { body: string; status: string; provider: string; sent_at: string } | null;
};

const RULE_TEXT: Record<Rule["trigger"], { title: string; description: string }> = {
  appointment: { title: "Recordatorio de cita", description: "A todas las citas programadas o confirmadas." },
  next_control: { title: "Refuerzo de vacuna, desparasitación o control", description: "Según la fecha de \"próximo control\" de la historia clínica." },
};

function offsetLabel(minutes: number): string {
  const m = Math.abs(minutes);
  if (m % 1440 === 0) return m === 1440 ? "1 día antes" : `${m / 1440} días antes`;
  return `${m / 60} horas antes`;
}

const HOURS = Array.from({ length: 17 }, (_, i) => `${String(i + 6).padStart(2, "0")}:00`); // 06:00–22:00

function formatWhen(iso: string): string {
  return new Date(iso).toLocaleString("es-CO", { weekday: "short", day: "numeric", month: "short", hour: "numeric", minute: "2-digit" }).replace(/\./g, "");
}

/**
 * Configuración → Recordatorios (Fase 4c): rules, sending window, usage and
 * the list of scheduled / sent / not sent reminders.
 */
export default function RemindersSection() {
  const canEdit = useCan("agenda.settings.manage");
  const settings = useQuery({ queryKey: ["reminders", "settings"], queryFn: () => api<{ data: Settings }>("reminders/settings").then((r) => r.data) });

  if (!settings.data) return <Skeleton className="h-96" />;
  const s = settings.data;

  return (
    <div className="space-y-6">
      {s.driver === "fake" && (
        <div className="rounded-xl border border-warning-200 bg-warning-50 px-4 py-3 text-theme-sm text-warning-800 dark:border-warning-500/30 dark:bg-warning-500/10 dark:text-warning-300">
          <b>Modo de prueba.</b> WhatsApp aún no está conectado: los recordatorios se programan y se “envían” con un conector simulado (quedan en la bitácora, pero no llegan a ningún celular). Al conectar la cuenta de Meta empiezan a salir de verdad, sin cambiar nada aquí.
        </div>
      )}
      {!s.plan.automatic && (
        <div className="rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 text-theme-sm text-gray-700 dark:border-gray-800 dark:bg-white/3 dark:text-gray-300">
          Tu plan ({s.plan.name}) incluye recordatorios <b>manuales</b> (botón “Recordar” en el inicio). Los automáticos están en el plan Pro.
        </div>
      )}

      <Usage settings={s} />
      <RulesForm key={JSON.stringify(s.rules) + JSON.stringify(s.window)} settings={s} canEdit={canEdit} />
      <ReminderList />
    </div>
  );
}

function Usage({ settings: s }: { settings: Settings }) {
  const limit = s.plan.monthly_limit;
  const pct = limit ? Math.min(100, Math.round((s.used_this_month / limit) * 100)) : 0;

  return (
    <Card className="p-5">
      <div className="flex flex-wrap items-end justify-between gap-4">
        <div>
          <p className="text-theme-sm text-gray-500 dark:text-gray-400">Mensajes automáticos este mes</p>
          <p className="mt-1 text-title-sm font-semibold text-gray-800 tabular dark:text-white/90">
            {s.used_this_month.toLocaleString("es-CO")}
            {limit !== null && <span className="text-theme-sm font-normal text-gray-500"> de {limit.toLocaleString("es-CO")}</span>}
          </p>
        </div>
        <div className="flex gap-4 text-theme-xs text-gray-500">
          <span>
            <b className="text-gray-800 tabular dark:text-white/90">{s.counts.sent ?? 0}</b> enviados
          </span>
          <span>
            <b className="text-gray-800 tabular dark:text-white/90">{(s.counts.pending ?? 0) + (s.counts.queued ?? 0)}</b> programados
          </span>
          <span>
            <b className="text-gray-800 tabular dark:text-white/90">{Object.entries(s.counts).filter(([k]) => k.startsWith("skipped") || k === "failed").reduce((a, [, v]) => a + v, 0)}</b> no enviados
          </span>
          <span className="text-gray-400">(últimos 30 días)</span>
        </div>
      </div>
      {limit !== null && limit > 0 && (
        <div className="mt-3 h-2 overflow-hidden rounded-full bg-gray-100 dark:bg-white/5" role="progressbar" aria-valuenow={pct} aria-valuemin={0} aria-valuemax={100} aria-label="Uso del límite mensual">
          <div className={cn("h-full rounded-full", pct >= 90 ? "bg-error-500" : pct >= 70 ? "bg-warning-500" : "bg-brand-500")} style={{ width: `${pct}%` }} />
        </div>
      )}
    </Card>
  );
}

function RulesForm({ settings: s, canEdit }: { settings: Settings; canEdit: boolean }) {
  const queryClient = useQueryClient();
  const [rules, setRules] = useState(s.rules);
  const [sendWindow, setSendWindow] = useState(s.window);
  const dirty = JSON.stringify(rules) !== JSON.stringify(s.rules) || JSON.stringify(sendWindow) !== JSON.stringify(s.window);

  const save = useMutation({
    mutationFn: () => api<{ data: Settings }>("reminders/settings", { method: "PUT", body: { window: sendWindow, rules: rules.map(({ id, is_active, offset_minutes }) => ({ id, is_active, offset_minutes })) } }),
    onSuccess: ({ data }) => {
      queryClient.setQueryData(["reminders", "settings"], data);
      queryClient.invalidateQueries({ queryKey: ["reminders", "list"] });
      queryClient.invalidateQueries({ queryKey: ["reminders", "due"] });
    },
  });
  const error = save.error instanceof ApiError ? (Object.values(save.error.errors)[0]?.[0] ?? save.error.message) : null;
  const setRule = (id: string, patch: Partial<Rule>) => setRules((all) => all.map((r) => (r.id === id ? { ...r, ...patch } : r)));

  return (
    <Card>
      <CardHeader title="Recordatorios automáticos por WhatsApp" description="Se envían solos, a nombre de la clínica, solo a tutores que autorizaron WhatsApp." />
      <ul className="divide-y divide-gray-100 dark:divide-gray-800">
        {rules.map((rule) => (
          <li key={rule.id} className="flex flex-wrap items-center gap-4 px-5 py-4">
            <div className="min-w-0 flex-1">
              <p className="text-theme-sm font-medium text-gray-800 dark:text-white/90">{RULE_TEXT[rule.trigger].title}</p>
              <p className="text-theme-xs text-gray-500 dark:text-gray-400">{RULE_TEXT[rule.trigger].description}</p>
            </div>
            <Select aria-label="Cuándo" className="w-40" value={rule.offset_minutes} disabled={!canEdit || !rule.is_active} onChange={(e) => setRule(rule.id, { offset_minutes: Number(e.target.value) })}>
              {rule.offsets.map((o) => (
                <option key={o} value={o}>
                  {offsetLabel(o)}
                </option>
              ))}
            </Select>
            <Switch checked={rule.is_active} label={RULE_TEXT[rule.trigger].title} disabled={!canEdit} onChange={(v) => setRule(rule.id, { is_active: v })} />
          </li>
        ))}
      </ul>

      <div className="grid gap-4 border-t border-gray-100 px-5 py-4 sm:grid-cols-3 dark:border-gray-800">
        <label className="text-theme-sm">
          <span className="mb-1.5 block font-medium text-gray-700 dark:text-gray-400">Enviar desde</span>
          <Select value={sendWindow.send_from} disabled={!canEdit} onChange={(e) => setSendWindow((w) => ({ ...w, send_from: e.target.value }))}>
            {HOURS.map((h) => (
              <option key={h} value={h}>
                {h}
              </option>
            ))}
          </Select>
        </label>
        <label className="text-theme-sm">
          <span className="mb-1.5 block font-medium text-gray-700 dark:text-gray-400">Hasta</span>
          <Select value={sendWindow.send_until} disabled={!canEdit} onChange={(e) => setSendWindow((w) => ({ ...w, send_until: e.target.value }))}>
            {HOURS.map((h) => (
              <option key={h} value={h}>
                {h}
              </option>
            ))}
          </Select>
        </label>
        <label className="text-theme-sm">
          <span className="mb-1.5 block font-medium text-gray-700 dark:text-gray-400">No recordar citas agendadas con menos de</span>
          <Select value={sendWindow.min_notice_hours} disabled={!canEdit} onChange={(e) => setSendWindow((w) => ({ ...w, min_notice_hours: Number(e.target.value) }))}>
            {[0, 1, 2, 3, 6, 12, 24].map((h) => (
              <option key={h} value={h}>
                {h === 0 ? "Siempre recordar" : `${h} ${h === 1 ? "hora" : "horas"}`}
              </option>
            ))}
          </Select>
        </label>
      </div>
      <p className="px-5 text-theme-xs text-gray-500">Fuera de este horario no se envía nada: si un recordatorio cae a las 6 a. m., sale cuando abre la ventana.</p>

      {canEdit && (
        <div className="flex items-center justify-end gap-3 px-5 py-4">
          {error && <span className="me-auto text-theme-xs text-error-500">{error}</span>}
          {save.isSuccess && !dirty && <span className="text-theme-xs text-success-600">Guardado. Los recordatorios ya programados se recalcularon.</span>}
          <Button size="sm" disabled={!dirty} loading={save.isPending} onClick={() => save.mutate()}>
            Guardar
          </Button>
        </div>
      )}
    </Card>
  );
}

const VIEWS = [
  ["upcoming", "Programados"],
  ["sent", "Enviados"],
  ["issues", "No enviados"],
] as const;

function ReminderList() {
  const [view, setView] = useState<(typeof VIEWS)[number][0]>("upcoming");
  const [page, setPage] = useState(1);
  const [open, setOpen] = useState<string | null>(null);

  const list = useQuery({
    queryKey: ["reminders", "list", view, page],
    queryFn: () => api<{ data: ReminderItem[]; meta: { current_page: number; last_page: number; total: number } }>(`reminders?view=${view}&page=${page}`),
    placeholderData: keepPreviousData,
  });

  return (
    <Card>
      <header className="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 px-5 py-4 dark:border-gray-800">
        <h2 className="text-base font-semibold text-gray-800 dark:text-white/90">Envíos</h2>
        <div role="tablist" className="inline-flex rounded-lg bg-gray-100 p-0.5 dark:bg-gray-900">
          {VIEWS.map(([key, label]) => (
            <button
              key={key}
              role="tab"
              type="button"
              aria-selected={view === key}
              onClick={() => (setView(key), setPage(1), setOpen(null))}
              className={cn("rounded-md px-3 py-1.5 text-theme-sm font-medium", view === key ? "bg-white text-gray-900 shadow-theme-xs dark:bg-gray-800 dark:text-white" : "text-gray-500 hover:text-gray-900 dark:text-gray-400")}
            >
              {label}
            </button>
          ))}
        </div>
      </header>

      {!list.data ? (
        <Skeleton className="m-5 h-24" />
      ) : list.data.data.length === 0 ? (
        <EmptyState title={view === "upcoming" ? "No hay recordatorios programados." : view === "sent" ? "Aún no se ha enviado ninguno." : "Todo se ha podido enviar."} />
      ) : (
        <ul className="divide-y divide-gray-100 dark:divide-gray-800">
          {list.data.data.map((r) => (
            <li key={r.id}>
              <button type="button" onClick={() => setOpen((o) => (o === r.id ? null : r.id))} className="flex w-full flex-wrap items-center gap-3 px-5 py-3 text-left hover:bg-gray-50 dark:hover:bg-white/2">
                <span className="w-36 shrink-0 text-theme-sm text-gray-700 tabular dark:text-gray-300">{formatWhen(r.message?.sent_at ?? r.scheduled_for)}</span>
                <span className="min-w-0 flex-1 truncate text-theme-sm">
                  <span className="font-medium text-gray-800 dark:text-white/90">{r.pet?.name ?? "—"}</span>
                  <span className="text-gray-500"> · {r.owner?.full_name} · {r.purpose === "appointment_reminder" ? "cita" : "vacuna o control"}</span>
                </span>
                <span
                  className={cn(
                    "rounded-full px-2.5 py-0.5 text-theme-xs font-medium",
                    r.status.value === "sent" ? "bg-success-50 text-success-700 dark:bg-success-500/15 dark:text-success-400" : view === "issues" ? "bg-warning-50 text-warning-700 dark:bg-warning-500/15 dark:text-warning-400" : "bg-gray-100 text-gray-600 dark:bg-white/5 dark:text-gray-300",
                  )}
                >
                  {r.status.label}
                  {r.message?.provider === "fake" && " (simulado)"}
                </span>
              </button>
              {open === r.id && (
                <div className="space-y-2 bg-gray-50 px-5 py-3 text-theme-sm dark:bg-white/2">
                  {r.message ? (
                    <p className="max-w-xl rounded-2xl rounded-tl-sm bg-white px-4 py-3 whitespace-pre-line text-gray-800 shadow-theme-xs dark:bg-gray-800 dark:text-gray-200">
                      <WhatsAppText text={r.message.body} />
                    </p>
                  ) : (
                    <p className="text-gray-600 dark:text-gray-400">{view === "upcoming" ? `Se enviará ${formatWhen(r.scheduled_for)}.` : r.status.label}</p>
                  )}
                  {r.last_error && <p className="text-theme-xs text-error-600">Último error: {r.last_error}</p>}
                  {r.pet && (
                    <Link href={`/app/mascotas/${r.pet.id}`} className="text-theme-xs font-medium text-brand-600 hover:underline dark:text-brand-400">
                      Ver ficha de {r.pet.name}
                    </Link>
                  )}
                </div>
              )}
            </li>
          ))}
        </ul>
      )}

      {list.data && list.data.meta.last_page > 1 && (
        <div className="flex items-center justify-between border-t border-gray-100 px-5 py-3 text-theme-sm text-gray-500 dark:border-gray-800">
          <span>
            Página {list.data.meta.current_page} de {list.data.meta.last_page}
          </span>
          <div className="flex gap-2">
            <Button size="sm" variant="outline" disabled={page <= 1} onClick={() => setPage((p) => p - 1)}>
              Anterior
            </Button>
            <Button size="sm" variant="outline" disabled={page >= list.data.meta.last_page} onClick={() => setPage((p) => p + 1)}>
              Siguiente
            </Button>
          </div>
        </div>
      )}
    </Card>
  );
}
