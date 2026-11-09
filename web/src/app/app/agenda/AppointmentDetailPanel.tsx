"use client";

import { appointmentTitle, STATUS_BADGE, STATUS_DOT } from "@/components/domain/appointmentStatus";
import PetAvatar from "@/components/domain/PetAvatar";
import WhatsAppReminderButton from "@/components/domain/WhatsAppReminderButton";
import Button from "@/components/ui/button/Button";
import { dayOf, formatMinutes, longDayLabel, minutesOf } from "@/lib/agenda";
import { api, ApiError } from "@/lib/api";
import { formatPhone, initials, whatsappLink } from "@/lib/patients";
import type { AppointmentDetail, AppointmentStatus, PetDetail } from "@/lib/types";
import { cn } from "@/utils";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useCan } from "@/components/providers/SessionProvider";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { useEffect, useRef, useState } from "react";
import { agendaKeys } from "./queries";

const FLOW: { status: AppointmentStatus; label: string }[] = [
  { status: "scheduled", label: "Programada" },
  { status: "confirmed", label: "Confirmada" },
  { status: "checked_in", label: "En sala" },
  { status: "in_progress", label: "En atención" },
  { status: "completed", label: "Atendida" },
];

/** Forward actions, most likely next step first. Cancel / no-show live apart. */
const PRIMARY_ORDER: AppointmentStatus[] = ["checked_in", "in_progress", "completed", "confirmed"];
const ACTION_LABEL: Partial<Record<AppointmentStatus, string>> = {
  confirmed: "Confirmar",
  checked_in: "Registrar llegada",
  in_progress: "Iniciar atención",
  completed: "Marcar atendida",
};
const EDITABLE: AppointmentStatus[] = ["scheduled", "confirmed", "checked_in"];

/**
 * Appointment side panel: the agenda stays visible behind it. Patient first
 * (alerts always visible), then when/what, the owner with one-tap contact,
 * the status progress and the actions pinned at the bottom.
 */
export default function AppointmentDetailPanel({
  appointmentId,
  canManage,
  onClose,
  onReschedule,
}: {
  appointmentId: string;
  canManage: boolean;
  onClose: () => void;
  onReschedule: (appointment: AppointmentDetail) => void;
}) {
  const queryClient = useQueryClient();
  const [cancelling, setCancelling] = useState(false);
  const [reason, setReason] = useState("");
  const panel = useRef<HTMLDivElement>(null);

  const detail = useQuery({
    queryKey: ["agenda", "appointment", appointmentId],
    queryFn: () => api<{ data: AppointmentDetail }>(`appointments/${appointmentId}`).then((r) => r.data),
  });
  const petId = detail.data?.pet?.id;
  const pet = useQuery({
    queryKey: ["pets", petId],
    queryFn: () => api<{ data: PetDetail }>(`pets/${petId}`).then((r) => r.data),
    enabled: !!petId,
    staleTime: 60_000,
  });

  const change = useMutation({
    mutationFn: (status: AppointmentStatus) =>
      api<{ data: AppointmentDetail }>(`appointments/${appointmentId}/status`, {
        method: "POST",
        body: { status, cancellation_reason: status === "cancelled" ? reason : undefined },
      }),
    onSuccess: ({ data }) => {
      queryClient.setQueryData(["agenda", "appointment", appointmentId], data);
      queryClient.invalidateQueries({ queryKey: agendaKeys.all });
      queryClient.invalidateQueries({ queryKey: ["appointments"] });
      queryClient.invalidateQueries({ queryKey: ["dashboard"] });
      setCancelling(false);
    },
  });

  // Escape closes; focus moves into the panel; page behind doesn't scroll.
  useEffect(() => {
    const onKey = (e: KeyboardEvent) => e.key === "Escape" && onClose();
    document.addEventListener("keydown", onKey);
    document.body.style.overflow = "hidden";
    panel.current?.focus();
    return () => {
      document.removeEventListener("keydown", onKey);
      document.body.style.overflow = "";
    };
  }, [onClose]);

  const a = detail.data;
  const error = change.error instanceof ApiError ? (change.error.field("cancellation_reason") ?? change.error.field("status") ?? change.error.message) : null;
  const next = (a?.allowed_next_statuses ?? []).map((s) => s.value);
  const forward = PRIMARY_ORDER.filter((s) => next.includes(s));
  const alerts = (pet.data?.alerts ?? []).filter((x) => x.is_active);

  return (
    <div className="fixed inset-0 z-99999" role="dialog" aria-modal="true" aria-labelledby="appt-panel-title">
      <div className="absolute inset-0 bg-gray-900/30 backdrop-blur-[2px] animate-[fadeIn_150ms_ease-out]" onClick={onClose} aria-hidden />

      <div
        ref={panel}
        tabIndex={-1}
        className="absolute inset-y-0 right-0 flex w-full max-w-md flex-col bg-white shadow-theme-xl outline-none animate-[slideIn_200ms_ease-out] dark:bg-gray-900"
      >
        {/* Header */}
        <div className="flex items-center justify-between border-b border-gray-100 px-6 py-4 dark:border-gray-800">
          {a ? (
            <span className={cn("inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-theme-xs font-medium", STATUS_BADGE[a.status.value])}>
              <span className={cn("h-1.5 w-1.5 rounded-full", STATUS_DOT[a.status.value])} aria-hidden />
              {a.status.label}
            </span>
          ) : (
            <span />
          )}
          <button type="button" onClick={onClose} aria-label="Cerrar" className="rounded-lg p-2 text-gray-400 hover:bg-gray-100 hover:text-gray-700 dark:hover:bg-white/5 dark:hover:text-gray-200">
            <svg width="20" height="20" viewBox="0 0 20 20" fill="none" aria-hidden>
              <path d="M5 5l10 10M15 5 5 15" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" />
            </svg>
          </button>
        </div>

        {!a ? (
          <div className="flex-1 space-y-4 p-6">
            {detail.isError ? (
              <p className="text-theme-sm text-error-600">No fue posible cargar la cita.</p>
            ) : (
              [1, 2, 3].map((i) => <div key={i} className="h-16 animate-pulse rounded-xl bg-gray-100 dark:bg-white/5" />)
            )}
          </div>
        ) : (
          <>
            <div className="flex-1 overflow-y-auto">
              {a.status.value === "requested" && a.booking && (
                <section className="mx-6 mt-6 rounded-xl border border-dashed border-[#7a5af8] bg-[#f4f3ff] p-4 text-theme-sm text-[#3e1c96] dark:bg-[#7a5af8]/10 dark:text-[#d9d6fe]">
                  <p className="font-semibold">Solicitud en línea sin responder</p>
                  <p className="mt-1">
                    {a.booking.name}
                    {a.booking.document_number ? ` (${a.booking.document_type} ${a.booking.document_number})` : ""} · {formatPhone(a.booking.phone)} pide la cita para <b>{a.booking.pet_name}</b> ({a.booking.species_name}).
                  </p>
                  {a.booking.notes && <p className="mt-1 italic">“{a.booking.notes}”</p>}
                  <Link href="/app/agenda/solicitudes" className="mt-3 inline-flex rounded-lg bg-[#6938ef] px-3 py-1.5 text-theme-xs font-semibold text-white hover:bg-[#5925dc]">
                    Aceptar o rechazar
                  </Link>
                </section>
              )}
              {/* Patient */}
              <section className="px-6 pt-6 pb-5">
                <div className="flex items-start gap-4">
                  <PetAvatar photoUrl={pet.data?.photo_url} speciesCode={pet.data?.species?.code} name={a.pet?.name} />
                  <div className="min-w-0 flex-1">
                    <h2 id="appt-panel-title" className="truncate text-xl font-semibold text-gray-900 dark:text-white">
                      {appointmentTitle(a)}
                    </h2>
                    {a.pet && (
                      <p className="mt-0.5 text-theme-sm text-gray-500 dark:text-gray-400">
                        {pet.data
                          ? [pet.data.species?.name, pet.data.breed?.name ?? pet.data.breed_other, pet.data.sex?.label, pet.data.age, pet.data.weight_kg && `${pet.data.weight_kg.toLocaleString("es-CO")} kg`]
                              .filter(Boolean)
                              .join(" · ")
                          : (a.pet.species ?? "")}
                      </p>
                    )}
                  </div>
                </div>

                {alerts.length > 0 && (
                  <ul className="mt-4 space-y-1.5">
                    {alerts.map((alert) => (
                      <li
                        key={alert.id}
                        className={cn(
                          "flex items-start gap-2 rounded-lg px-3 py-2 text-theme-sm",
                          alert.type.value === "behavior"
                            ? "bg-warning-50 text-warning-800 dark:bg-warning-500/10 dark:text-warning-300"
                            : "bg-error-50 text-error-800 dark:bg-error-500/10 dark:text-error-300",
                        )}
                      >
                        <svg width="16" height="16" viewBox="0 0 20 20" fill="none" className="mt-0.5 shrink-0" aria-hidden>
                          <path d="M10 7v4m0 3h.01M8.6 3.4 2.2 14.5A1.6 1.6 0 0 0 3.6 17h12.8a1.6 1.6 0 0 0 1.4-2.5L11.4 3.4a1.6 1.6 0 0 0-2.8 0Z" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" />
                        </svg>
                        <span>
                          <span className="font-semibold">{alert.type.label}:</span> {alert.description}
                        </span>
                      </li>
                    ))}
                  </ul>
                )}
              </section>

              {/* When / what */}
              <section className="mx-6 grid gap-3 rounded-xl border border-gray-100 p-4 dark:border-gray-800">
                <InfoRow icon={<ClockIcon />}>
                  <span className="block font-medium text-gray-800 first-letter:uppercase dark:text-white/90">{longDayLabel(dayOf(a.starts_at)).replace(/ de \d{4}$/, "")}</span>
                  <span className="block text-gray-500 dark:text-gray-400">
                    {a.is_time_unset ? "Sin hora fija · por orden de llegada" : `${formatMinutes(minutesOf(a.starts_at))} – ${formatMinutes(minutesOf(a.ends_at))}`}
                  </span>
                </InfoRow>
                <InfoRow icon={<StethoscopeIcon />}>
                  <span className="font-medium text-gray-800 dark:text-white/90">{a.service?.name ?? "Sin servicio"}</span>
                  <span className="block text-gray-500 dark:text-gray-400">con {a.users.map((u) => u.name).join(", ") || "profesional sin asignar"}</span>
                </InfoRow>
              </section>

              {/* Owner */}
              {a.owner && (
                <section className="px-6 pt-6">
                  <h3 className="mb-2 text-theme-xs font-semibold tracking-wide text-gray-400 uppercase">Tutor</h3>
                  <div className="flex items-center gap-3">
                    <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-gray-100 text-theme-sm font-semibold text-gray-600 dark:bg-white/5 dark:text-gray-300">
                      {initials(a.owner.full_name)}
                    </span>
                    <div className="min-w-0 flex-1">
                      <p className="truncate text-theme-sm font-medium text-gray-800 dark:text-white/90">{a.owner.full_name}</p>
                      {a.owner.mobile_phone && <p className="text-theme-xs text-gray-500 tabular">{formatPhone(a.owner.mobile_phone)}</p>}
                    </div>
                    {a.owner.mobile_phone && (
                      <div className="flex gap-1.5">
                        {["scheduled", "confirmed"].includes(a.status.value) && canManage && (
                          <WhatsAppReminderButton type="appointment" id={a.id} allowed label="Recordar" />
                        )}
                        <a
                          href={whatsappLink(a.owner.mobile_phone)}
                          target="_blank"
                          rel="noopener noreferrer"
                          aria-label="Escribir por WhatsApp"
                          title="WhatsApp"
                          className="flex h-9 w-9 items-center justify-center rounded-lg border border-gray-200 text-[#1f9d55] hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-white/5"
                        >
                          <WhatsAppIcon />
                        </a>
                        <a
                          href={`tel:${a.owner.mobile_phone}`}
                          aria-label="Llamar"
                          title="Llamar"
                          className="flex h-9 w-9 items-center justify-center rounded-lg border border-gray-200 text-gray-600 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/5"
                        >
                          <PhoneIcon />
                        </a>
                      </div>
                    )}
                  </div>
                </section>
              )}

              {/* Progress */}
              <section className="px-6 pt-6">
                <h3 className="mb-3 text-theme-xs font-semibold tracking-wide text-gray-400 uppercase">Progreso</h3>
                {a.status.value === "cancelled" || a.status.value === "no_show" ? (
                  <div className={cn("rounded-lg px-3 py-2.5 text-theme-sm", STATUS_BADGE[a.status.value], "no-underline")}>
                    <p className="font-medium">{a.status.label}</p>
                    {a.cancellation_reason && <p className="mt-0.5 opacity-90">Motivo: {a.cancellation_reason}</p>}
                  </div>
                ) : (
                  <Stepper current={a.status.value} />
                )}
              </section>

              {(a.internal_notes || a.status_history.length > 0) && (
                <section className="px-6 pt-6 pb-6">
                  {a.internal_notes && (
                    <div className="mb-4 rounded-lg bg-gray-50 px-3 py-2.5 text-theme-sm text-gray-700 dark:bg-white/3 dark:text-gray-300">
                      <span className="mb-0.5 block text-theme-xs font-semibold text-gray-400 uppercase">Nota interna</span>
                      {a.internal_notes}
                    </div>
                  )}
                  {a.status_history.length > 0 && (
                    <details className="group text-theme-sm">
                      <summary className="cursor-pointer list-none text-theme-xs font-semibold tracking-wide text-gray-400 uppercase hover:text-gray-600">
                        Historial <span className="inline-block transition group-open:rotate-90">›</span>
                      </summary>
                      <ol className="mt-3 space-y-3">
                        {a.status_history.map((h, i) => (
                          <li key={i} className="flex gap-3">
                            <span className={cn("mt-1.5 h-2 w-2 shrink-0 rounded-full", STATUS_DOT[h.to as AppointmentStatus] ?? "bg-gray-300")} aria-hidden />
                            <div className="text-theme-xs text-gray-500 dark:text-gray-400">
                              <span className="font-medium text-gray-800 dark:text-white/90">{h.label}</span>
                              <span className="block">
                                {dayOf(h.at) === dayOf(a.starts_at) ? "" : `${dayOf(h.at)} · `}
                                {formatMinutes(minutesOf(h.at))}
                                {h.changed_by && ` · ${h.changed_by}`}
                                {h.channel !== "staff" && ` · vía ${h.channel}`}
                              </span>
                              {h.note && <span className="block italic">{h.note}</span>}
                            </div>
                          </li>
                        ))}
                      </ol>
                    </details>
                  )}
                </section>
              )}
            </div>

            {/* Actions pinned at the bottom */}
            {canManage && (forward.length > 0 || EDITABLE.includes(a.status.value) || next.length > 0) && (
              <div className="border-t border-gray-100 bg-white px-6 py-4 dark:border-gray-800 dark:bg-gray-900">
                {cancelling ? (
                  <form
                    className="space-y-3"
                    onSubmit={(e) => {
                      e.preventDefault();
                      change.mutate("cancelled");
                    }}
                  >
                    <label htmlFor="cancel-reason" className="block text-theme-sm font-medium text-gray-700 dark:text-gray-300">
                      ¿Por qué se cancela?
                    </label>
                    <input
                      id="cancel-reason"
                      autoFocus
                      value={reason}
                      onChange={(e) => setReason(e.target.value)}
                      placeholder="Ej.: el tutor reprogramará por viaje"
                      className="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-theme-sm focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 focus:outline-hidden dark:border-gray-700 dark:text-white/90"
                    />
                    {error && <p className="text-theme-xs text-error-500">{error}</p>}
                    <div className="flex gap-2">
                      <Button type="button" variant="outline" size="sm" className="flex-1" onClick={() => setCancelling(false)}>
                        Volver
                      </Button>
                      <Button type="submit" variant="danger" size="sm" className="flex-1" disabled={!reason.trim()} loading={change.isPending}>
                        Cancelar cita
                      </Button>
                    </div>
                  </form>
                ) : (
                  <>
                    {error && <p className="mb-3 text-theme-xs text-error-500">{error}</p>}
                    {/* Main next step alone and full width; secondary ones below. */}
                    {forward.slice(0, 1).map((s) => (
                      <Button key={s} className="w-full" loading={change.isPending && change.variables === s} onClick={() => change.mutate(s)}>
                        {ACTION_LABEL[s]}
                      </Button>
                    ))}
                    {(forward.length > 1 || EDITABLE.includes(a.status.value)) && (
                      <div className="mt-2 flex gap-2">
                        {forward.slice(1, 2).map((s) => (
                          <Button key={s} size="sm" variant="outline" className="flex-1" loading={change.isPending && change.variables === s} onClick={() => change.mutate(s)}>
                            {ACTION_LABEL[s]}
                          </Button>
                        ))}
                        {EDITABLE.includes(a.status.value) && (
                          <Button size="sm" variant="outline" className="flex-1" onClick={() => onReschedule(a)} title="Editar o reprogramar">
                            Reprogramar
                          </Button>
                        )}
                      </div>
                    )}
                    {(next.includes("no_show") || next.includes("cancelled")) && (
                      <div className="mt-3 flex justify-center gap-5 text-theme-sm">
                        {next.includes("no_show") && (
                          <button type="button" onClick={() => change.mutate("no_show")} className="font-medium text-gray-500 hover:text-gray-800 dark:hover:text-gray-200">
                            No asistió
                          </button>
                        )}
                        {next.includes("cancelled") && (
                          <button type="button" onClick={() => setCancelling(true)} className="font-medium text-error-600 hover:text-error-700 dark:text-error-400">
                            Cancelar cita
                          </button>
                        )}
                      </div>
                    )}
                  </>
                )}
              </div>
            )}

            {a.owner && !["cancelled", "no_show", "requested"].includes(a.status.value) && <ChargeButton appointmentId={a.id} />}

            {(a.pet || a.owner) && (
              <div className="flex justify-center gap-6 border-t border-gray-100 px-6 py-3 dark:border-gray-800">
                {a.pet && (
                  <Link href={`/app/mascotas/${a.pet.id}`} className="text-theme-xs font-medium text-brand-600 hover:underline dark:text-brand-400">
                    Historia clínica de {a.pet.name}
                  </Link>
                )}
                {a.owner && (
                  <Link href={`/app/tutores/${a.owner.id}`} className="text-theme-xs font-medium text-gray-500 hover:text-gray-800 hover:underline dark:hover:text-gray-200">
                    Ficha del tutor
                  </Link>
                )}
              </div>
            )}
          </>
        )}
      </div>
    </div>
  );
}

function Stepper({ current }: { current: AppointmentStatus }) {
  const index = FLOW.findIndex((s) => s.status === current);

  return (
    <ol className="flex items-start">
      {FLOW.map((step, i) => {
        const done = i < index;
        const active = i === index;
        return (
          <li key={step.status} className="relative flex flex-1 flex-col items-center text-center">
            {i > 0 && <span className={cn("absolute top-2.5 right-1/2 h-0.5 w-full -translate-y-1/2", i <= index ? "bg-brand-500" : "bg-gray-200 dark:bg-gray-700")} aria-hidden />}
            <span
              className={cn(
                "relative z-1 flex h-5 w-5 items-center justify-center rounded-full border-2",
                done && "border-brand-500 bg-brand-500 text-white",
                active && "border-brand-500 bg-white ring-4 ring-brand-500/15 dark:bg-gray-900",
                !done && !active && "border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900",
              )}
            >
              {done && (
                <svg width="10" height="10" viewBox="0 0 12 12" fill="none" aria-hidden>
                  <path d="M2.5 6.2 4.8 8.5 9.5 3.5" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" />
                </svg>
              )}
              {active && <span className="h-2 w-2 rounded-full bg-brand-500" />}
            </span>
            <span className={cn("mt-1.5 text-[11px] leading-tight", active ? "font-semibold text-brand-700 dark:text-brand-300" : done ? "text-gray-600 dark:text-gray-400" : "text-gray-400")}>
              {step.label}
            </span>
          </li>
        );
      })}
    </ol>
  );
}

function InfoRow({ icon, children }: { icon: React.ReactNode; children: React.ReactNode }) {
  return (
    <div className="flex gap-3 text-theme-sm">
      <span className="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-brand-50 text-brand-600 dark:bg-brand-500/10 dark:text-brand-400">{icon}</span>
      <div className="min-w-0">{children}</div>
    </div>
  );
}

const ClockIcon = () => (
  <svg width="16" height="16" viewBox="0 0 20 20" fill="none" aria-hidden>
    <circle cx="10" cy="10" r="7.25" stroke="currentColor" strokeWidth="1.6" />
    <path d="M10 6v4l2.5 1.5" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" />
  </svg>
);

const StethoscopeIcon = () => (
  <svg width="16" height="16" viewBox="0 0 20 20" fill="none" aria-hidden>
    <path d="M5 3H4v5a4 4 0 0 0 8 0V3h-1M8 12v1.5a4 4 0 0 0 8 0V11" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" />
    <circle cx="16" cy="9.5" r="1.5" stroke="currentColor" strokeWidth="1.6" />
  </svg>
);

const PhoneIcon = () => (
  <svg width="16" height="16" viewBox="0 0 20 20" fill="none" aria-hidden>
    <path d="M4.5 3h2.6l1.3 3.3-1.7 1a8 8 0 0 0 4 4l1-1.7 3.3 1.3v2.6A1.5 1.5 0 0 1 13.5 15 10.5 10.5 0 0 1 3 4.5 1.5 1.5 0 0 1 4.5 3Z" stroke="currentColor" strokeWidth="1.6" strokeLinejoin="round" />
  </svg>
);

const WhatsAppIcon = () => (
  <svg width="17" height="17" viewBox="0 0 24 24" fill="currentColor" aria-hidden>
    <path d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.3A10 10 0 1 0 12 2Zm0 18.2a8.2 8.2 0 0 1-4.2-1.2l-.3-.2-3 .8.8-2.9-.2-.3A8.2 8.2 0 1 1 12 20.2Zm4.5-6.1c-.2-.1-1.5-.7-1.7-.8-.2-.1-.4-.1-.6.1l-.8 1c-.1.2-.3.2-.5.1a6.7 6.7 0 0 1-3.3-2.9c-.2-.4.2-.4.7-1.3.1-.2 0-.3 0-.4l-.8-1.8c-.2-.5-.4-.4-.6-.4h-.5a1 1 0 0 0-.7.3 3 3 0 0 0-.9 2.2 5.2 5.2 0 0 0 1.1 2.7 11.8 11.8 0 0 0 4.5 4c1.7.7 2.3.8 3.2.6.5-.1 1.5-.6 1.7-1.2.2-.6.2-1.1.2-1.2-.1-.1-.3-.2-.5-.3Z" />
  </svg>
);

/**
 * "Cobrar": opens the owner's account of the day with this appointment's
 * service (or the sale where it was already charged). docs/inventario.md, V3.
 */
function ChargeButton({ appointmentId }: { appointmentId: string }) {
  const canSell = useCan("sales.manage");
  const router = useRouter();
  const charge = useMutation({
    mutationFn: () => api<{ data: { sale_id: string } }>(`appointments/${appointmentId}/charge`, { method: "POST" }).then((r) => r.data),
    onSuccess: (d) => router.push(`/app/ventas/${d.sale_id}`),
  });
  if (!canSell) return null;

  return (
    <div className="border-t border-gray-100 px-6 py-3 dark:border-gray-800">
      <Button variant="outline" className="w-full" onClick={() => charge.mutate()} loading={charge.isPending}>
        Cobrar
      </Button>
      {charge.error && <p className="mt-2 text-theme-xs text-error-600">{charge.error instanceof ApiError ? (Object.values(charge.error.errors)[0]?.[0] ?? charge.error.message) : charge.error.message}</p>}
    </div>
  );
}
