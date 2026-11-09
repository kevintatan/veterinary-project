"use client";

import { Card, CardHeader, EmptyState, Skeleton } from "@/components/common/Card";
import PageHeader from "@/components/common/PageHeader";
import AppointmentStatusBadge from "@/components/domain/AppointmentStatusBadge";
import { useCan, useSession } from "@/components/providers/SessionProvider";
import { api } from "@/lib/api";
import { formatLongDate, formatTime, greeting, todayIn } from "@/lib/dates";
import type { Appointment, Paginated, Task } from "@/lib/types";
import { cn } from "@/utils";
import { useQuery } from "@tanstack/react-query";
import { appointmentTitle } from "@/components/domain/appointmentStatus";
import Link from "next/link";
import { Suspense } from "react";
import ActivitySection from "./ActivitySection";
import IncomeCard from "./IncomeCard";
import OnboardingCard from "./OnboardingCard";
import RemindersCard from "./RemindersCard";

/**
 * "Hoy en la clínica" (docs/design.md §3): today's work, not vanity charts.
 */
export default function TodayDashboard() {
  const { user, clinic } = useSession();
  const canSeeAgenda = useCan("appointments.view");
  const tz = clinic.timezone;
  const today = todayIn(tz);

  const appointments = useQuery({
    queryKey: ["appointments", { from: today, to: today }],
    queryFn: () => api<{ data: Appointment[] }>(`appointments?from=${today}&to=${today}`).then((r) => r.data),
    enabled: canSeeAgenda,
    refetchInterval: 60_000, // the reception screen stays open all day
  });

  const tasks = useQuery({
    queryKey: ["tasks", { scope: "mine", status: "pending" }],
    queryFn: () => api<Paginated<Task>>("tasks?scope=mine&status=pending"),
  });

  const list = appointments.data ?? [];
  const active = list.filter((a) => !["cancelled", "no_show"].includes(a.status.value));
  const waiting = list.filter((a) => a.status.value === "checked_in");
  const toConfirm = list.filter((a) => a.status.value === "scheduled");
  const done = list.filter((a) => a.status.value === "completed");

  return (
    <>
      <PageHeader
        title={`${greeting(tz)}, ${user.name.split(" ")[0]}`}
        subtitle={<span className="first-letter:uppercase">{formatLongDate(tz)} · {clinic.name}</span>}
        actions={
          canSeeAgenda && (
            <Link href="/app/agenda" className="inline-flex items-center rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white shadow-theme-xs hover:bg-brand-600">
              Ir a la agenda
            </Link>
          )
        }
      />

      {/* useSearchParams inside (?bienvenida=1 after sign-up) → Suspense boundary. */}
      <Suspense>
        <OnboardingCard />
      </Suspense>

      <IncomeCard />

      {canSeeAgenda && (
        <div className="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
          <Counter label="Citas de hoy" value={active.length} loading={appointments.isLoading} />
          <Counter label="Por confirmar" value={toConfirm.length} loading={appointments.isLoading} tone={toConfirm.length > 0 ? "accent" : "default"} />
          <Counter label="En sala de espera" value={waiting.length} loading={appointments.isLoading} tone={waiting.length > 0 ? "warning" : "default"} />
          <Counter label="Atendidas" value={done.length} loading={appointments.isLoading} />
        </div>
      )}

      <div className="grid gap-6 xl:grid-cols-3">
        {canSeeAgenda && (
          <Card className="xl:col-span-2">
            <CardHeader title="Agenda de hoy" description="Por hora, con su estado actual." />
            {appointments.isLoading ? (
              <div className="space-y-3 p-5">
                {[0, 1, 2].map((i) => <Skeleton key={i} className="h-14" />)}
              </div>
            ) : appointments.isError ? (
              <EmptyState title="No fue posible cargar la agenda." description="Revisa tu conexión e intenta de nuevo." />
            ) : list.length === 0 ? (
              <EmptyState title="No hay citas para hoy." description="Las citas que agendes aparecerán aquí." />
            ) : (
              <ul className="divide-y divide-gray-100 dark:divide-gray-800">
                {list.map((a) => (
                  <li key={a.id} className={cn("flex items-center gap-4 px-5 py-3", ["cancelled", "no_show"].includes(a.status.value) && "opacity-60")}>
                    <span className="w-20 shrink-0 text-theme-sm font-semibold text-gray-800 tabular dark:text-white/90">
                      {a.is_time_unset ? "Sin hora" : formatTime(a.starts_at, tz)}
                    </span>
                    <span
                      className="h-9 w-1 shrink-0 rounded-full"
                      style={{ backgroundColor: a.service?.color ?? "var(--color-gray-300)" }}
                      aria-hidden
                    />
                    <div className="min-w-0 flex-1">
                      <p className="truncate text-theme-sm font-medium text-gray-800 dark:text-white/90">
                        {appointmentTitle(a)}
                        {a.owner && <span className="font-normal text-gray-500"> · {a.owner.full_name}</span>}
                      </p>
                      <p className="truncate text-theme-xs text-gray-500">
                        {a.service?.name ?? "Sin servicio"}
                        {a.users.length > 0 && ` · ${a.users.map((u) => u.name).join(", ")}`}
                        {a.is_home_visit && " · A domicilio"}
                      </p>
                    </div>
                    <AppointmentStatusBadge status={a.status} />
                  </li>
                ))}
              </ul>
            )}
          </Card>
        )}

        <div className="space-y-6">
          {canSeeAgenda && (
            <Card>
              <CardHeader title="Sala de espera" description="Pacientes que ya llegaron." />
              {appointments.isLoading ? (
                <div className="p-5">
                  <Skeleton className="h-10" />
                </div>
              ) : waiting.length === 0 ? (
                <EmptyState title="Nadie en espera." />
              ) : (
                <ul className="divide-y divide-gray-100 dark:divide-gray-800">
                  {waiting.map((a) => (
                    <li key={a.id} className="flex items-center justify-between gap-3 px-5 py-3">
                      <div className="min-w-0">
                        <p className="truncate text-theme-sm font-medium text-gray-800 dark:text-white/90">{a.pet?.name ?? "Sin mascota"}</p>
                        <p className="truncate text-theme-xs text-gray-500">{a.users[0]?.name ?? "Sin asignar"}</p>
                      </div>
                      <span className="text-theme-xs text-gray-500 tabular">Cita {formatTime(a.starts_at, tz)}</span>
                    </li>
                  ))}
                </ul>
              )}
            </Card>
          )}

          <Card>
            <CardHeader
              title="Mis tareas"
              description="Pendientes asignadas a ti."
              action={
                <Link href="/app/tareas" className="text-theme-sm font-medium text-brand-600 hover:underline">
                  Ver todas
                </Link>
              }
            />
            {tasks.isLoading ? (
              <div className="space-y-3 p-5">
                <Skeleton className="h-10" />
                <Skeleton className="h-10" />
              </div>
            ) : (tasks.data?.data ?? []).length === 0 ? (
              <EmptyState title="No tienes tareas pendientes." />
            ) : (
              <ul className="divide-y divide-gray-100 dark:divide-gray-800">
                {tasks.data!.data.slice(0, 6).map((t) => (
                  <li key={t.id} className="px-5 py-3">
                    <p className="text-theme-sm font-medium text-gray-800 dark:text-white/90">{t.title}</p>
                    <p className={cn("text-theme-xs", t.is_overdue ? "font-medium text-error-600" : "text-gray-500")}>
                      {t.is_overdue ? "Vencida" : t.due_at ? `Vence ${formatTime(t.due_at, tz)}` : "Sin fecha límite"}
                      {t.pet && ` · ${t.pet.name}`}
                    </p>
                  </li>
                ))}
              </ul>
            )}
          </Card>
        </div>
      </div>

      {canSeeAgenda && <RemindersCard />}

      {canSeeAgenda && <ActivitySection />}
    </>
  );
}

function Counter({
  label,
  value,
  loading,
  tone = "default",
}: {
  label: string;
  value: number;
  loading: boolean;
  tone?: "default" | "accent" | "warning";
}) {
  return (
    <Card className="px-5 py-4">
      <p className="text-theme-sm text-gray-500 dark:text-gray-400">{label}</p>
      {loading ? (
        <Skeleton className="mt-2 h-8 w-12" />
      ) : (
        <p
          className={cn(
            "mt-1 text-title-sm font-semibold tabular",
            tone === "accent" && "text-accent-600",
            tone === "warning" && "text-warning-600",
            tone === "default" && "text-gray-800 dark:text-white/90",
          )}
        >
          {value}
        </p>
      )}
    </Card>
  );
}
