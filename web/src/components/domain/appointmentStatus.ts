import type { AppointmentStatus } from "@/lib/types";

/**
 * Fixed colors per appointment status (docs/design.md §2): one source for the
 * badge, the agenda blocks and any legend.
 */
export const STATUS_BADGE: Record<AppointmentStatus, string> = {
  requested: "bg-[#f4f3ff] text-[#5925dc] dark:bg-[#7a5af8]/15 dark:text-[#bdb4fe]",
  scheduled: "bg-gray-100 text-gray-700 dark:bg-white/5 dark:text-gray-300",
  confirmed: "bg-brand-50 text-brand-700 dark:bg-brand-500/15 dark:text-brand-300",
  checked_in: "bg-warning-50 text-warning-700 dark:bg-warning-500/15 dark:text-warning-400",
  in_progress: "bg-blue-light-50 text-blue-light-700 dark:bg-blue-light-500/15 dark:text-blue-light-400",
  completed: "bg-success-50 text-success-700 dark:bg-success-500/15 dark:text-success-400",
  cancelled: "bg-error-50 text-error-700 line-through dark:bg-error-500/15 dark:text-error-400",
  no_show: "bg-accent-50 text-accent-700 dark:bg-accent-500/15 dark:text-accent-300",
};

export const STATUS_DOT: Record<AppointmentStatus, string> = {
  requested: "bg-status-requested",
  scheduled: "bg-status-scheduled",
  confirmed: "bg-status-confirmed",
  checked_in: "bg-status-checked-in",
  in_progress: "bg-status-in-progress",
  completed: "bg-status-completed",
  cancelled: "bg-status-cancelled",
  no_show: "bg-status-no-show",
};

/** Agenda block: soft tint + a strong left edge in the status color. */
export const STATUS_BLOCK: Record<AppointmentStatus, string> = {
  // Online request waiting for the clinic: dashed, it isn't a real appointment yet.
  requested: "border-status-requested border-dashed bg-[#f4f3ff] dark:bg-[#7a5af8]/10",
  scheduled: "border-status-scheduled bg-gray-50 dark:bg-white/5",
  confirmed: "border-status-confirmed bg-brand-50 dark:bg-brand-500/15",
  checked_in: "border-status-checked-in bg-warning-50 dark:bg-warning-500/15",
  in_progress: "border-status-in-progress bg-blue-light-50 dark:bg-blue-light-500/15",
  completed: "border-status-completed bg-success-50 dark:bg-success-500/10",
  cancelled: "border-status-cancelled bg-error-50/60 opacity-70 dark:bg-error-500/10",
  no_show: "border-status-no-show bg-accent-50 opacity-80 dark:bg-accent-500/10",
};

export const STATUS_LABEL: Record<AppointmentStatus, string> = {
  requested: "Solicitud en línea",
  scheduled: "Programada",
  confirmed: "Confirmada",
  checked_in: "En sala de espera",
  in_progress: "En atención",
  completed: "Atendida",
  cancelled: "Cancelada",
  no_show: "No asistió",
};

/** What to call an appointment in the agenda: its pet, or the pet asked for in an online request. */
export function appointmentTitle(a: { pet?: { name: string } | null; booking?: { pet_name: string } | null }, empty = "Espacio reservado"): string {
  return a.pet?.name ?? (a.booking ? `${a.booking.pet_name} · solicitud` : empty);
}
