"use client";

import Button from "@/components/ui/button/Button";
import { Modal } from "@/components/ui/modal";
import OwnerPetPicker from "@/components/domain/OwnerPetPicker";
import { useSession } from "@/components/providers/SessionProvider";
import { type Day, formatMinutes, minutesOf, toLocalDateTime } from "@/lib/agenda";
import { todayIn } from "@/lib/dates";
import { api, ApiError } from "@/lib/api";
import type { Appointment, OwnerWithPets, Professional } from "@/lib/types";
import { cn } from "@/utils";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useState } from "react";
import { agendaKeys, useServices } from "./queries";

export type AppointmentDraft = {
  day: Day;
  minutes: number | null; // null = pick a time
  userId: string | null;
  appointment?: Appointment; // set when rescheduling / editing
  preset?: { owner: { id: string; full_name: string }; petId: string }; // from the pet record
};

const field =
  "h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-theme-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90";
const label = "mb-1.5 block text-theme-sm font-medium text-gray-700 dark:text-gray-400";

/**
 * Create an appointment, or edit/reschedule an existing one. Overlaps, closed
 * hours and blocks are validated by the API; its messages are shown as they come.
 */
export default function AppointmentFormModal({
  draft,
  branchId,
  professionals,
  defaultUserId,
  onClose,
}: {
  draft: AppointmentDraft;
  branchId: string;
  professionals: Professional[];
  defaultUserId: string | null;
  onClose: () => void;
}) {
  const editing = draft.appointment;
  const queryClient = useQueryClient();
  const services = useServices();

  const [owner, setOwner] = useState<Pick<OwnerWithPets, "id" | "full_name"> | null>(editing?.owner ?? draft.preset?.owner ?? null);
  const [petId, setPetId] = useState<string>(editing?.pet?.id ?? draft.preset?.petId ?? "");
  const [serviceId, setServiceId] = useState<string>(editing?.service?.id ?? "");
  const [chosenUser, setUserId] = useState<string>(draft.userId ?? "");
  // The professionals list may arrive after the form opened (e.g. from a pet record).
  const userId = chosenUser || defaultUserId || "";
  const [day, setDay] = useState<string>(draft.day);
  const [time, setTime] = useState<string>(draft.minutes === null ? "" : toLocalDateTime(draft.day, draft.minutes).slice(11));
  const [duration, setDuration] = useState<number | null>(editing ? minutesOf(editing.ends_at) - minutesOf(editing.starts_at) : null);
  const [timeUnset, setTimeUnset] = useState<boolean>(editing?.is_time_unset ?? false);
  const [notes, setNotes] = useState<string>(editing?.internal_notes ?? "");

  const service = services.data?.find((s) => s.id === serviceId);
  const minutes = duration ?? service?.duration_minutes ?? 30;

  const save = useMutation({
    mutationFn: () => {
      const start = timeUnset ? `${day}T00:00` : `${day}T${time}`;
      const [h, m] = (timeUnset ? "00:00" : time).split(":").map(Number);
      const end = toLocalDateTime(day, Math.min(h * 60 + m + minutes, 24 * 60 - 1));
      const body = {
        branch_id: branchId,
        owner_id: owner?.id ?? null,
        pet_id: petId || null,
        service_id: serviceId || null,
        starts_at: start,
        ends_at: timeUnset ? null : end,
        is_time_unset: timeUnset,
        user_ids: [userId],
        internal_notes: notes || null,
      };

      return editing
        ? api<{ data: Appointment }>(`appointments/${editing.id}`, { method: "PATCH", body })
        : api<{ data: Appointment }>("appointments", { method: "POST", body });
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: agendaKeys.all });
      queryClient.invalidateQueries({ queryKey: ["appointments"] });
      queryClient.invalidateQueries({ queryKey: ["dashboard"] });
      onClose();
    },
  });

  const error = save.error instanceof ApiError ? save.error : null;
  const fieldError = (name: string) => error?.field(name);
  const generalError = error && Object.keys(error.errors).length === 0 ? error.message : (error?.field("starts_at") ?? error?.field("user_ids") ?? error?.field("status"));
  const canSubmit = !!userId && !!day && (timeUnset || !!time) && !save.isPending;

  return (
    <Modal isOpen onClose={onClose} className="m-4 max-w-xl p-6 sm:p-8">
      <h2 className="mb-1 text-lg font-semibold text-gray-800 dark:text-white/90">{editing ? "Editar o reprogramar cita" : "Nueva cita"}</h2>
      <p className="mb-6 text-theme-sm text-gray-500 dark:text-gray-400">
        {editing ? "Cambia la fecha, la hora o el profesional." : "Busca el tutor por nombre, documento, celular o nombre de la mascota."}
      </p>

      <form
        className="space-y-4"
        onSubmit={(e) => {
          e.preventDefault();
          if (canSubmit) save.mutate();
        }}
      >
        <OwnerPetPicker
          id="appt-owner"
          hint="opcional para reservar el espacio"
          autoFocus
          owner={owner}
          petId={petId}
          onChange={(o, pet) => {
            setOwner(o);
            setPetId(pet);
          }}
          error={fieldError("owner_id") ?? fieldError("pet_id")}
        />

        <div>
          <label htmlFor="appt-service" className={label}>
            Servicio
          </label>
          <select id="appt-service" className={field} value={serviceId} onChange={(e) => (setServiceId(e.target.value), setDuration(null))}>
            <option value="">Sin servicio</option>
            {services.data?.map((s) => (
              <option key={s.id} value={s.id}>
                {s.name} · {s.duration_minutes} min
              </option>
            ))}
          </select>
          {fieldError("service_id") && <p className="mt-1 text-theme-xs text-error-500">{fieldError("service_id")}</p>}
        </div>

        <div>
          <label htmlFor="appt-user" className={label}>
            Profesional
          </label>
          <select id="appt-user" className={field} value={userId} onChange={(e) => setUserId(e.target.value)} required>
            <option value="" disabled>
              Elige un profesional
            </option>
            {professionals.map((p) => (
              <option key={p.id} value={p.id}>
                {p.name}
              </option>
            ))}
          </select>
        </div>

        <div className="grid grid-cols-2 gap-4 sm:grid-cols-3">
          <div className="col-span-2 sm:col-span-1">
            <label htmlFor="appt-day" className={label}>
              Fecha
            </label>
            <input id="appt-day" type="date" className={field} value={day} onChange={(e) => setDay(e.target.value)} required />
          </div>
          <div>
            <label htmlFor="appt-time" className={label}>
              Hora
            </label>
            <input id="appt-time" type="time" step={300} className={field} value={time} onChange={(e) => setTime(e.target.value)} disabled={timeUnset} required={!timeUnset} />
          </div>
          <div>
            <label htmlFor="appt-duration" className={label}>
              Duración
            </label>
            <select id="appt-duration" className={field} value={minutes} onChange={(e) => setDuration(Number(e.target.value))} disabled={timeUnset}>
              {[...new Set([15, 20, 30, 45, 60, 90, 120, 180, 240, minutes])]
                .sort((a, b) => a - b)
                .map((m) => (
                  <option key={m} value={m}>
                    {m < 60 ? `${m} min` : `${m / 60} h`.replace(".5", ",5")}
                  </option>
                ))}
            </select>
          </div>
        </div>

        {!timeUnset && userId && day && (
          <SlotSuggestions
            branchId={branchId}
            day={day}
            userId={userId}
            serviceId={serviceId}
            selected={time}
            onPick={setTime}
          />
        )}

        <label className="flex items-center gap-2 text-theme-sm text-gray-700 dark:text-gray-400">
          <input type="checkbox" checked={timeUnset} onChange={(e) => setTimeUnset(e.target.checked)} className="h-4 w-4 rounded border-gray-300 accent-brand-500" />
          Sin hora fija (se atiende por orden de llegada)
        </label>

        <div>
          <label htmlFor="appt-notes" className={label}>
            Nota interna <span className="font-normal text-gray-400">(opcional)</span>
          </label>
          <textarea
            id="appt-notes"
            rows={2}
            className={cn(field, "h-auto py-2")}
            value={notes}
            onChange={(e) => setNotes(e.target.value)}
            placeholder="Ej.: viene en ayunas, es agresivo con extraños…"
          />
        </div>

        {generalError && (
          <p role="alert" className="rounded-lg bg-error-50 px-3 py-2 text-theme-sm text-error-700 dark:bg-error-500/15 dark:text-error-400">
            {generalError}
          </p>
        )}

        <div className="flex items-center justify-between gap-3 pt-2">
          <p className="text-theme-xs text-gray-500">{!timeUnset && time && `Termina a las ${formatMinutes(Math.min(Number(time.slice(0, 2)) * 60 + Number(time.slice(3, 5)) + minutes, 24 * 60 - 1))}`}</p>
          <div className="flex gap-3">
            <Button type="button" variant="outline" size="sm" onClick={onClose}>
              Cancelar
            </Button>
            <Button type="submit" size="sm" disabled={!canSubmit} loading={save.isPending}>
              {editing ? "Guardar cambios" : "Agendar"}
            </Button>
          </div>
        </div>
      </form>
    </Modal>
  );
}

type Availability = { user: { id: string; name: string }; slots: { starts_at: string; ends_at: string }[] }[];

/**
 * Free times for that professional and day (the API applies schedules, blocks
 * and existing appointments). Picking one fills the time field.
 */
function SlotSuggestions({
  branchId,
  day,
  userId,
  serviceId,
  selected,
  onPick,
}: {
  branchId: string;
  day: string;
  userId: string;
  serviceId: string;
  selected: string;
  onPick: (time: string) => void;
}) {
  const { clinic } = useSession();
  const valid = /^\d{4}-\d{2}-\d{2}$/.test(day);
  const availability = useQuery({
    queryKey: ["agenda", "availability", branchId, day, userId, serviceId],
    queryFn: () =>
      api<{ data: Availability }>(`appointments/availability?branch_id=${branchId}&date=${day}&user_id=${userId}${serviceId ? `&service_id=${serviceId}` : ""}`).then((r) => r.data),
    enabled: valid,
    staleTime: 15_000,
  });

  const now = nowMinutes(clinic.timezone);
  const isToday = day === todayIn(clinic.timezone);
  const slots = (availability.data?.find((row) => row.user.id === userId)?.slots ?? [])
    .map((slot) => minutesOf(slot.starts_at))
    .filter((m) => !isToday || m > now);

  return (
    <div>
      <p className="mb-1.5 text-theme-xs font-medium text-gray-500 dark:text-gray-400">Horarios libres</p>
      {availability.isLoading ? (
        <div className="flex gap-1.5">
          {[1, 2, 3, 4, 5].map((i) => (
            <span key={i} className="h-8 w-16 animate-pulse rounded-lg bg-gray-100 dark:bg-white/5" />
          ))}
        </div>
      ) : slots.length === 0 ? (
        <p className="rounded-lg bg-gray-50 px-3 py-2 text-theme-xs text-gray-500 dark:bg-white/3 dark:text-gray-400">
          No hay espacios libres ese día para este profesional. Prueba otra fecha o escribe la hora a mano.
        </p>
      ) : (
        <div className="flex max-h-28 flex-wrap gap-1.5 overflow-y-auto">
          {slots.map((m) => {
            const value = `${String(Math.floor(m / 60)).padStart(2, "0")}:${String(m % 60).padStart(2, "0")}`;
            const active = selected.slice(0, 5) === value;
            return (
              <button
                key={m}
                type="button"
                onClick={() => onPick(value)}
                aria-pressed={active}
                className={cn(
                  "rounded-lg border px-2.5 py-1.5 text-theme-xs font-medium tabular transition",
                  active
                    ? "border-brand-500 bg-brand-500 text-white"
                    : "border-gray-200 text-gray-700 hover:border-brand-300 hover:bg-brand-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-brand-500/10",
                )}
              >
                {formatMinutes(m)}
              </button>
            );
          })}
        </div>
      )}
    </div>
  );
}

function nowMinutes(timeZone: string): number {
  const parts = new Intl.DateTimeFormat("en-GB", { timeZone, hour: "2-digit", minute: "2-digit", hourCycle: "h23" }).formatToParts(new Date());
  return Number(parts.find((p) => p.type === "hour")?.value) * 60 + Number(parts.find((p) => p.type === "minute")?.value);
}
