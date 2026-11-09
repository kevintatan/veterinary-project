"use client";

import { Card, EmptyState, Skeleton } from "@/components/common/Card";
import PageHeader from "@/components/common/PageHeader";
import { useCan, useSession } from "@/components/providers/SessionProvider";
import Button from "@/components/ui/button/Button";
import { api } from "@/lib/api";
import { formatTime, todayIn } from "@/lib/dates";
import type { Paginated, Task } from "@/lib/types";
import { cn } from "@/utils";
import { useInfiniteQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import Link from "next/link";
import { useState } from "react";
import TaskFormPanel from "./TaskFormPanel";

type Scope = "mine" | "created" | "all";
type Status = Task["status"]["value"];

const SCOPES: [Scope, string][] = [
  ["mine", "Mías"],
  ["created", "Creadas por mí"],
  ["all", "Toda la clínica"],
];

const STATUSES: [Status, string][] = [
  ["pending", "Pendientes"],
  ["done", "Hechas"],
  ["discarded", "Descartadas"],
];

const shortDay = (iso: string) => new Date(`${iso.slice(0, 10)}T12:00:00Z`).toLocaleDateString("es-CO", { weekday: "short", day: "numeric", month: "short", timeZone: "UTC" }).replace(/\./g, "");

/**
 * Team tasks (Fase 4f): what someone has to do (call an owner back, order
 * a product, check a lab result…). Pending ones are grouped by urgency.
 */
export default function TasksView() {
  const { clinic } = useSession();
  const tz = clinic.timezone;
  const canCreate = useCan("tasks.manage");
  const queryClient = useQueryClient();
  const [scope, setScope] = useState<Scope>("mine");
  const [status, setStatus] = useState<Status>("pending");
  const [editing, setEditing] = useState<Task | "new" | null>(null);

  const tasks = useInfiniteQuery({
    queryKey: ["tasks", { scope, status }],
    queryFn: ({ pageParam }) => api<Paginated<Task>>(`tasks?scope=${scope}&status=${status}&page=${pageParam}`),
    initialPageParam: 1,
    getNextPageParam: (last) => (last.meta.current_page < last.meta.last_page ? last.meta.current_page + 1 : undefined),
  });

  const complete = useMutation({
    mutationFn: (t: Task) => api(`tasks/${t.id}`, { method: "PATCH", body: { status: t.status.value === "pending" ? "done" : "pending" } }),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ["tasks"] }),
  });

  const list = tasks.data?.pages.flatMap((p) => p.data) ?? [];
  const total = tasks.data?.pages[0]?.meta.total ?? 0;
  const today = todayIn(tz);

  const groups: [string, Task[]][] =
    status === "pending"
      ? [
          ["Vencidas", list.filter((t) => t.is_overdue)],
          ["Hoy", list.filter((t) => !t.is_overdue && t.due_at?.slice(0, 10) === today)],
          ["Próximas", list.filter((t) => !t.is_overdue && t.due_at && t.due_at.slice(0, 10) > today)],
          ["Sin fecha", list.filter((t) => !t.due_at)],
        ]
      : [["", list]];

  const due = (t: Task) => {
    if (!t.due_at) return null;
    const day = t.due_at.slice(0, 10);
    const time = t.due_at.slice(11, 16) === "23:59" ? "" : `, ${formatTime(t.due_at, tz)}`;
    return day === today ? `Hoy${time}` : `${shortDay(t.due_at)}${time}`;
  };

  return (
    <>
      <PageHeader
        title="Tareas"
        subtitle="Pendientes del equipo: llamar a un tutor, revisar un resultado, pedir un producto…"
        actions={canCreate && <Button onClick={() => setEditing("new")}>Nueva tarea</Button>}
      />

      <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div className="inline-flex rounded-lg bg-gray-100 p-0.5 dark:bg-gray-900">
          {SCOPES.map(([value, label]) => (
            <button
              key={value}
              type="button"
              onClick={() => setScope(value)}
              className={cn(
                "rounded-md px-3 py-2 text-theme-sm font-medium",
                scope === value ? "bg-white text-gray-900 shadow-theme-xs dark:bg-gray-800 dark:text-white" : "text-gray-500 hover:text-gray-700 dark:text-gray-400",
              )}
            >
              {label}
            </button>
          ))}
        </div>
        <div className="flex gap-1">
          {STATUSES.map(([value, label]) => (
            <button
              key={value}
              type="button"
              onClick={() => setStatus(value)}
              className={cn(
                "rounded-full px-3 py-1.5 text-theme-xs font-medium",
                status === value ? "bg-gray-900 text-white dark:bg-white dark:text-gray-900" : "bg-gray-100 text-gray-600 hover:bg-gray-200 dark:bg-white/5 dark:text-gray-300",
              )}
            >
              {label}
              {status === value && !tasks.isLoading ? ` ${total}` : ""}
            </button>
          ))}
        </div>
      </div>

      <Card>
        {tasks.isLoading ? (
          <div className="space-y-3 p-5">
            <Skeleton className="h-12" />
            <Skeleton className="h-12" />
            <Skeleton className="h-12" />
          </div>
        ) : list.length === 0 ? (
          <EmptyState
            title={status === "pending" ? "Nada pendiente por aquí." : status === "done" ? "Aún no hay tareas hechas." : "No hay tareas descartadas."}
            description={status === "pending" && canCreate ? "Crea una con «Nueva tarea»." : undefined}
          />
        ) : (
          groups
            .filter(([, items]) => items.length > 0)
            .map(([title, items]) => (
              <section key={title || "all"}>
                {title && (
                  <h2 className={cn("border-b border-gray-100 px-5 pt-4 pb-2 text-theme-xs font-semibold tracking-wide uppercase dark:border-gray-800", title === "Vencidas" ? "text-error-600" : "text-gray-500")}>
                    {title} <span className="font-normal">({items.length})</span>
                  </h2>
                )}
                <ul className="divide-y divide-gray-100 dark:divide-gray-800">
                  {items.map((t) => (
                    <li key={t.id} className="flex items-start gap-3 px-5 py-3 hover:bg-gray-50 dark:hover:bg-white/2">
                      {status !== "discarded" && (
                        <button
                          type="button"
                          disabled={!t.can_edit || complete.isPending}
                          onClick={() => complete.mutate(t)}
                          aria-label={t.status.value === "pending" ? `Marcar "${t.title}" como hecha` : `Reabrir "${t.title}"`}
                          title={t.can_edit ? undefined : "Solo quien la creó, su responsable o un administrador"}
                          className={cn(
                            "mt-0.5 flex size-5 shrink-0 items-center justify-center rounded-md border-2 text-[11px] font-bold text-white transition",
                            t.status.value === "done" ? "border-success-500 bg-success-500" : "border-gray-300 hover:border-success-500 dark:border-gray-600",
                            !t.can_edit && "cursor-not-allowed opacity-50",
                          )}
                        >
                          {t.status.value === "done" && "✓"}
                        </button>
                      )}
                      <button type="button" onClick={() => setEditing(t)} className="min-w-0 flex-1 text-left">
                        <p className={cn("text-theme-sm font-medium text-gray-800 dark:text-white/90", t.status.value !== "pending" && "text-gray-500 line-through dark:text-gray-500")}>{t.title}</p>
                        {t.description && <p className="truncate text-theme-xs text-gray-500">{t.description}</p>}
                        <p className="mt-0.5 flex flex-wrap gap-x-2 text-theme-xs text-gray-500">
                          {due(t) && <span className={cn(t.is_overdue && "font-medium text-error-600")}>{t.is_overdue ? `Venció ${due(t)!.toLowerCase()}` : due(t)}</span>}
                          {scope !== "mine" && t.assignee && <span>· {t.assignee.name}</span>}
                          {t.completed_at && <span>· hecha {shortDay(t.completed_at)}</span>}
                        </p>
                      </button>
                      {(t.pet || t.owner) && (
                        <Link
                          href={t.pet ? `/app/mascotas/${t.pet.id}` : `/app/tutores/${t.owner!.id}`}
                          className="shrink-0 rounded-full bg-gray-100 px-2.5 py-1 text-theme-xs font-medium text-gray-700 hover:bg-brand-50 hover:text-brand-700 dark:bg-white/5 dark:text-gray-300"
                        >
                          {t.pet?.name ?? t.owner?.full_name}
                        </Link>
                      )}
                    </li>
                  ))}
                </ul>
              </section>
            ))
        )}
        {tasks.hasNextPage && (
          <div className="border-t border-gray-100 p-3 text-center dark:border-gray-800">
            <Button size="sm" variant="ghost" loading={tasks.isFetchingNextPage} onClick={() => tasks.fetchNextPage()}>
              Ver más
            </Button>
          </div>
        )}
      </Card>

      {editing && <TaskFormPanel task={editing === "new" ? null : editing} onClose={() => setEditing(null)} />}
    </>
  );
}
