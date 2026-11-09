"use client";

import { Card, EmptyState, Skeleton } from "@/components/common/Card";
import PetAvatar from "@/components/domain/PetAvatar";
import WhatsAppReminderButton from "@/components/domain/WhatsAppReminderButton";
import { useCan } from "@/components/providers/SessionProvider";
import { formatMinutes, minutesOf } from "@/lib/agenda";
import { api } from "@/lib/api";
import { shortDate } from "@/lib/clinical";
import { cn } from "@/utils";
import { useQuery } from "@tanstack/react-query";
import Link from "next/link";
import { useState } from "react";

type Contact = {
  owner: { id: string; full_name: string; mobile_phone: string | null } | null;
  whatsapp_allowed: boolean;
  whatsapp_blocked_reason: string | null;
  last_reminder: { sent_at: string; provider: string; by: string | null } | null;
  automatic: { status: { value: string; label: string }; scheduled_for: string } | null;
};

type DueAppointment = Contact & {
  id: string;
  starts_at: string;
  is_time_unset: boolean;
  status: { value: string; label: string };
  service: string | null;
  pet: { id: string; name: string };
};

type DueControl = Contact & {
  id: string;
  type: { value: string; label: string };
  summary: string | null;
  due_on: string;
  pet: { id: string; name: string };
  has_upcoming_appointment: boolean;
};

type Due = { date: string; appointments: DueAppointment[]; controls: DueControl[] };

/**
 * "Para recordar": tomorrow's appointments and vaccines/controls due this week,
 * each with a one-click WhatsApp reminder (Fase 4a, manual).
 */
export default function RemindersCard() {
  const canSend = useCan("appointments.manage");
  const [tab, setTab] = useState<"appointments" | "controls">("appointments");

  const due = useQuery({
    queryKey: ["reminders", "due"],
    queryFn: () => api<{ data: Due }>("reminders/due").then((r) => r.data),
  });

  const appointments = due.data?.appointments ?? [];
  const controls = due.data?.controls ?? [];
  const pendingAppointments = appointments.filter((a) => !a.last_reminder && a.status.value === "scheduled").length;

  return (
    <Card className="mt-6">
      <header className="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 px-5 py-4 dark:border-gray-800">
        <div>
          <h2 className="text-base font-semibold text-gray-800 dark:text-white/90">Para recordar</h2>
          <p className="mt-0.5 text-theme-sm text-gray-500 dark:text-gray-400">
            {pendingAppointments > 0 ? `${pendingAppointments} ${pendingAppointments === 1 ? "cita de mañana sin recordar" : "citas de mañana sin recordar"}.` : "Un clic abre WhatsApp con el mensaje listo."}
          </p>
        </div>
        <div role="tablist" aria-label="Qué recordar" className="inline-flex rounded-lg bg-gray-100 p-0.5 dark:bg-gray-900">
          {(
            [
              ["appointments", `Citas de mañana`, appointments.length],
              ["controls", "Vacunas y controles", controls.length],
            ] as const
          ).map(([key, text, count]) => (
            <button
              key={key}
              role="tab"
              type="button"
              aria-selected={tab === key}
              onClick={() => setTab(key)}
              className={cn(
                "rounded-md px-3 py-1.5 text-theme-sm font-medium",
                tab === key ? "bg-white text-gray-900 shadow-theme-xs dark:bg-gray-800 dark:text-white" : "text-gray-500 hover:text-gray-900 dark:text-gray-400",
              )}
            >
              {text} <span className="tabular opacity-60">{due.data ? count : ""}</span>
            </button>
          ))}
        </div>
      </header>

      {due.isLoading ? (
        <div className="space-y-3 p-5">
          <Skeleton className="h-12" />
          <Skeleton className="h-12" />
        </div>
      ) : due.isError ? (
        <EmptyState title="No fue posible cargar los recordatorios." />
      ) : tab === "appointments" ? (
        appointments.length === 0 ? (
          <EmptyState title="No hay citas pendientes para mañana." />
        ) : (
          <ul className="divide-y divide-gray-100 dark:divide-gray-800">
            {appointments.map((a) => (
              <Row
                key={a.id}
                item={a}
                title={a.pet.name}
                petId={a.pet.id}
                detail={[a.is_time_unset ? "Sin hora fija" : formatMinutes(minutesOf(a.starts_at)), a.service, a.status.value === "confirmed" ? "Ya confirmada" : null].filter(Boolean).join(" · ")}
                action={canSend && <WhatsAppReminderButton type="appointment" id={a.id} allowed={a.whatsapp_allowed} blockedReason={a.whatsapp_blocked_reason} />}
              />
            ))}
          </ul>
        )
      ) : controls.length === 0 ? (
        <EmptyState title="No hay vacunas ni controles en los próximos 7 días." />
      ) : (
        <ul className="divide-y divide-gray-100 dark:divide-gray-800">
          {controls.map((c) => (
            <Row
              key={c.id}
              item={c}
              title={c.pet.name}
              petId={c.pet.id}
              detail={[`${c.type.label}${c.summary ? `: ${c.summary}` : ""}`, `vence ${shortDate(c.due_on)}`, c.has_upcoming_appointment ? "ya tiene cita" : null].filter(Boolean).join(" · ")}
              action={canSend && <WhatsAppReminderButton type="control" id={c.id} allowed={c.whatsapp_allowed} blockedReason={c.whatsapp_blocked_reason} />}
            />
          ))}
        </ul>
      )}
    </Card>
  );
}

function Row({ item, title, petId, detail, action }: { item: Contact; title: string; petId: string; detail: string; action: React.ReactNode }) {
  const last = item.last_reminder;

  return (
    <li className="flex flex-wrap items-center gap-4 px-5 py-3">
      <PetAvatar size="sm" />
      <div className="min-w-0 flex-1">
        <p className="truncate text-theme-sm font-medium text-gray-800 dark:text-white/90">
          <Link href={`/app/mascotas/${petId}`} className="hover:text-brand-700 dark:hover:text-brand-300">
            {title}
          </Link>
          {item.owner && <span className="font-normal text-gray-500"> · {item.owner.full_name}</span>}
        </p>
        <p className="truncate text-theme-xs text-gray-500">{detail}</p>
      </div>
      {item.automatic && ["pending", "queued"].includes(item.automatic.status.value) && !last ? (
        <span className="rounded-full bg-brand-50 px-2.5 py-1 text-theme-xs font-medium text-brand-700 dark:bg-brand-500/15 dark:text-brand-300" title="El sistema lo enviará solo">
          Automático: {new Date(item.automatic.scheduled_for).toLocaleString("es-CO", { weekday: "short", hour: "numeric", minute: "2-digit" }).replace(/\./g, "")}
        </span>
      ) : last ? (
        <span className="text-theme-xs text-success-700 dark:text-success-400" title={last.by ? `Por ${last.by}` : undefined}>
          {last.provider === "manual" ? "Recordado" : "Enviado automáticamente"} {relative(last.sent_at)}
        </span>
      ) : (
        !item.whatsapp_allowed && <span className="max-w-48 text-right text-theme-xs text-gray-400">{item.whatsapp_blocked_reason}</span>
      )}
      {action}
    </li>
  );
}

function relative(iso: string): string {
  const minutes = Math.round((Date.now() - new Date(iso).getTime()) / 60_000);
  if (minutes < 1) return "ahora";
  if (minutes < 60) return `hace ${minutes} min`;
  if (minutes < 24 * 60) return `hace ${Math.round(minutes / 60)} h`;
  return `hace ${Math.round(minutes / 1440)} d`;
}
