import type { AppointmentStatus, Labelled } from "@/lib/types";
import { cn } from "@/utils";
import { STATUS_BADGE, STATUS_DOT } from "./appointmentStatus";

export default function AppointmentStatusBadge({ status, className }: { status: Labelled<AppointmentStatus>; className?: string }) {
  return (
    <span className={cn("inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-theme-xs font-medium whitespace-nowrap", STATUS_BADGE[status.value], className)}>
      <span className={cn("h-1.5 w-1.5 rounded-full", STATUS_DOT[status.value])} aria-hidden />
      {status.label}
    </span>
  );
}
