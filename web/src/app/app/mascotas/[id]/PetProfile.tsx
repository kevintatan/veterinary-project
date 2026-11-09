"use client";

import { Card, CardHeader, EmptyState, Skeleton } from "@/components/common/Card";
import AppointmentStatusBadge from "@/components/domain/AppointmentStatusBadge";
import PetAvatar from "@/components/domain/PetAvatar";
import PetPhotoUploader from "@/components/domain/PetPhotoUploader";
import { Field, Input, Select } from "@/components/form/fields";
import { useCan, useSession } from "@/components/providers/SessionProvider";
import DocumentsCard from "./DocumentsCard";
import Button from "@/components/ui/button/Button";
import { addDays, dayOf, formatMinutes, longDayLabel, minutesOf } from "@/lib/agenda";
import { api, ApiError } from "@/lib/api";
import { todayIn } from "@/lib/dates";
import { ALERT_SEVERITY, ALERT_TYPES, formatPhone, type Pet, petSummary } from "@/lib/patients";
import type { Appointment, PetAlert } from "@/lib/types";
import { cn } from "@/utils";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import Link from "next/link";
import { useState } from "react";
import PetFormPanel from "../PetFormPanel";
import ClinicalHistory from "./ClinicalHistory";
import PetCardSection from "./PetCardSection";

export default function PetProfile({ id }: { id: string }) {
  const canManage = useCan("patients.manage");
  const canBook = useCan("appointments.manage");
  const canSeeHistory = useCan("clinical_records.view");
  const [editing, setEditing] = useState(false);

  const pet = useQuery({
    queryKey: ["pets", id],
    queryFn: () => api<{ data: Pet }>(`pets/${id}`).then((r) => r.data),
  });

  if (pet.isLoading) {
    return (
      <div className="space-y-6">
        <Skeleton className="h-40" />
        <Skeleton className="h-64" />
      </div>
    );
  }

  if (pet.isError || !pet.data) {
    const notFound = pet.error instanceof ApiError && pet.error.status === 404;
    return (
      <Card>
        <EmptyState title={notFound ? "Esta mascota no existe o fue eliminada." : "No fue posible cargar la mascota."} />
      </Card>
    );
  }

  const p = pet.data;

  return (
    <>
      <nav aria-label="Ruta" className="mb-4 text-theme-sm text-gray-500">
        <Link href="/app/tutores" className="hover:text-gray-800 dark:hover:text-gray-200">
          Tutores
        </Link>
        <span className="mx-2 text-gray-300">/</span>
        <Link href={`/app/tutores/${p.owner.id}`} className="hover:text-gray-800 dark:hover:text-gray-200">
          {p.owner.full_name}
        </Link>
        <span className="mx-2 text-gray-300">/</span>
        <span className="text-gray-800 dark:text-white/90">{p.name}</span>
      </nav>

      {/* Header */}
      <Card className="mb-6 p-5 sm:p-6">
        <div className="flex flex-wrap items-start gap-5">
          {canManage ? (
            <PetPhotoUploader petId={p.id} name={p.name} photoUrl={p.photo_url} speciesCode={p.species?.code} />
          ) : (
            <PetAvatar photoUrl={p.photo_url} speciesCode={p.species?.code} name={p.name} size="lg" />
          )}
          <div className="min-w-0 flex-1">
            <div className="flex flex-wrap items-center gap-2">
              <h1 className="text-title-sm font-semibold text-gray-900 dark:text-white">{p.name}</h1>
              {p.deceased_at && <span className="rounded-full bg-gray-100 px-2.5 py-0.5 text-theme-xs font-medium text-gray-600 dark:bg-white/5 dark:text-gray-400">Falleció el {p.deceased_at}</span>}
            </div>
            <p className="mt-1 text-theme-sm text-gray-500 dark:text-gray-400">{petSummary(p)}</p>
            <p className="mt-2 text-theme-sm text-gray-600 dark:text-gray-400">
              Tutor:{" "}
              <Link href={`/app/tutores/${p.owner.id}`} className="font-medium text-gray-800 hover:text-brand-700 dark:text-white/90">
                {p.owner.full_name}
              </Link>
              {p.owner.mobile_phone && <span className="text-gray-500"> · {formatPhone(p.owner.mobile_phone)}</span>}
            </p>
          </div>
          <div className="flex flex-wrap gap-2">
            {canBook && !p.deceased_at && (
              <Link href={`/app/agenda?nueva=${p.id}`} className="inline-flex items-center rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white shadow-theme-xs hover:bg-brand-600">
                Agendar cita
              </Link>
            )}
            {canManage && (
              <Button size="sm" variant="outline" onClick={() => setEditing(true)}>
                Editar
              </Button>
            )}
          </div>
        </div>

        <dl className="mt-6 grid gap-x-8 gap-y-4 border-t border-gray-100 pt-5 text-theme-sm sm:grid-cols-3 lg:grid-cols-6 dark:border-gray-800">
          <Detail term="Nacimiento">{birthLabel(p)}</Detail>
          <Detail term="Estado reproductivo">{p.reproductive_status?.label}</Detail>
          <Detail term="Temperamento">{p.temperament?.label}</Detail>
          <Detail term="Tamaño · Color">{[p.size?.label, p.color].filter(Boolean).join(" · ")}</Detail>
          <Detail term="Microchip">{p.microchip}</Detail>
          <Detail term="Alimentación">{p.diet}</Detail>
        </dl>

        {p.important_notes && (
          <p className="mt-5 rounded-lg bg-warning-50 px-3 py-2.5 text-theme-sm text-warning-800 dark:bg-warning-500/10 dark:text-warning-300">
            <span className="font-semibold">Nota: </span>
            {p.important_notes}
          </p>
        )}
      </Card>

      <div className="grid gap-6 xl:grid-cols-3">
        <div className="xl:col-span-2">{canSeeHistory ? <ClinicalHistory pet={p} /> : <UpcomingAppointments petId={p.id} />}</div>
        <div className="space-y-6">
          <AlertsCard pet={p} canManage={canManage} />
          {canSeeHistory && <UpcomingAppointments petId={p.id} />}
          {canSeeHistory && <DocumentsCard petId={p.id} />}
          <PetCardSection pet={p} canManage={canManage} />
        </div>
      </div>

      {editing && <PetFormPanel ownerId={p.owner.id} ownerName={p.owner.full_name} pet={p} onClose={() => setEditing(false)} />}
    </>
  );
}

/** "14 may 2022", or "≈ oct 2024" when the date was estimated from an age. */
function birthLabel(pet: Pet): string | null {
  if (!pet.birth_date) return null;
  const date = new Date(`${pet.birth_date}T00:00:00Z`);
  const options: Intl.DateTimeFormatOptions = pet.birth_date_is_estimated
    ? { month: "short", year: "numeric", timeZone: "UTC" }
    : { day: "numeric", month: "short", year: "numeric", timeZone: "UTC" };
  const text = new Intl.DateTimeFormat("es-CO", options).format(date).replace(/\./g, "").replace(/ de /g, " ");
  return pet.birth_date_is_estimated ? `≈ ${text}` : text;
}

function Detail({ term, children }: { term: string; children: React.ReactNode }) {
  return (
    <div className="min-w-0">
      <dt className="text-theme-xs text-gray-500 dark:text-gray-400">{term}</dt>
      <dd className="mt-0.5 truncate text-gray-800 dark:text-white/90">{children || <span className="text-gray-400">—</span>}</dd>
    </div>
  );
}

function UpcomingAppointments({ petId }: { petId: string }) {
  const { clinic } = useSession();
  const today = todayIn(clinic.timezone);

  const appointments = useQuery({
    queryKey: ["appointments", { pet: petId, from: today }],
    queryFn: () => api<{ data: Appointment[] }>(`appointments?pet_id=${petId}&from=${today}&to=${addDays(today, 60)}`).then((r) => r.data),
  });

  const list = (appointments.data ?? []).filter((a) => !["cancelled", "completed", "no_show"].includes(a.status.value));

  return (
    <Card>
      <CardHeader title="Próximas citas" description="Los siguientes 60 días." />
      {appointments.isLoading ? (
        <div className="p-5">
          <Skeleton className="h-12" />
        </div>
      ) : list.length === 0 ? (
        <EmptyState title="No tiene citas próximas." />
      ) : (
        <ul className="divide-y divide-gray-100 dark:divide-gray-800">
          {list.map((a) => (
            <li key={a.id}>
              {/* Two lines (when / what) so it fits the narrow side column: nothing gets squeezed. */}
              <Link href={`/app/agenda?fecha=${dayOf(a.starts_at)}`} className="block px-5 py-3 hover:bg-gray-50 dark:hover:bg-white/2">
                <div className="flex items-center justify-between gap-3">
                  <p className="min-w-0 truncate text-theme-sm font-medium text-gray-800 first-letter:uppercase dark:text-white/90">
                    {longDayLabel(dayOf(a.starts_at)).replace(/ de \d{4}$/, "")}
                    <span className="font-normal text-gray-500 tabular"> · {a.is_time_unset ? "Sin hora fija" : formatMinutes(minutesOf(a.starts_at))}</span>
                  </p>
                  <AppointmentStatusBadge status={a.status} />
                </div>
                <p className="mt-0.5 truncate text-theme-xs text-gray-500">
                  {a.service?.name ?? "Sin servicio"}
                  {a.users[0] && ` · con ${a.users[0].name}`}
                </p>
              </Link>
            </li>
          ))}
        </ul>
      )}
    </Card>
  );
}

/** Allergies and behavior warnings: always visible here, in the agenda and in the clinical record. */
function AlertsCard({ pet, canManage }: { pet: Pet; canManage: boolean }) {
  const queryClient = useQueryClient();
  const [adding, setAdding] = useState(false);
  const [draft, setDraft] = useState<{ type: PetAlert["type"]["value"]; severity: PetAlert["severity"]["value"]; description: string }>({
    type: "allergy",
    severity: "high",
    description: "",
  });
  const refresh = () => {
    queryClient.invalidateQueries({ queryKey: ["pets", pet.id] });
    queryClient.invalidateQueries({ queryKey: ["owners"] });
  };

  const create = useMutation({
    mutationFn: () => api(`pets/${pet.id}/alerts`, { method: "POST", body: draft }),
    onSuccess: () => {
      refresh();
      setAdding(false);
      setDraft({ type: "allergy", severity: "high", description: "" });
    },
  });

  const toggle = useMutation({
    mutationFn: (alert: PetAlert) => api(`pets/${pet.id}/alerts/${alert.id}`, { method: "PATCH", body: { is_active: !alert.is_active } }),
    onSuccess: refresh,
  });

  const active = pet.alerts.filter((a) => a.is_active);
  const inactive = pet.alerts.filter((a) => !a.is_active);
  const error = create.error instanceof ApiError ? (create.error.field("description") ?? create.error.message) : null;

  return (
    <Card className="h-fit">
      <CardHeader
        title="Alertas"
        description="Alergias, comportamiento y condiciones."
        action={
          canManage &&
          !adding && (
            <button type="button" onClick={() => setAdding(true)} className="shrink-0 text-theme-sm font-medium text-brand-600 hover:underline dark:text-brand-400">
              + Agregar
            </button>
          )
        }
      />

      {adding && (
        <form
          className="space-y-3 border-b border-gray-100 p-5 dark:border-gray-800"
          onSubmit={(e) => {
            e.preventDefault();
            if (draft.description.trim()) create.mutate();
          }}
        >
          <div className="grid grid-cols-2 gap-3">
            <Field label="Tipo" htmlFor="al-type">
              <Select id="al-type" value={draft.type} onChange={(e) => setDraft((d) => ({ ...d, type: e.target.value as typeof d.type }))}>
                {ALERT_TYPES.map(([v, l]) => (
                  <option key={v} value={v}>
                    {l}
                  </option>
                ))}
              </Select>
            </Field>
            <Field label="Importancia" htmlFor="al-sev">
              <Select id="al-sev" value={draft.severity} onChange={(e) => setDraft((d) => ({ ...d, severity: e.target.value as typeof d.severity }))}>
                {ALERT_SEVERITY.map(([v, l]) => (
                  <option key={v} value={v}>
                    {l}
                  </option>
                ))}
              </Select>
            </Field>
          </div>
          <Field label="Descripción" htmlFor="al-desc" error={error ?? undefined}>
            <Input id="al-desc" autoFocus value={draft.description} onChange={(e) => setDraft((d) => ({ ...d, description: e.target.value }))} placeholder="Ej.: Penicilina, reacción cutánea" maxLength={255} />
          </Field>
          <div className="flex justify-end gap-2">
            <Button type="button" size="sm" variant="outline" onClick={() => setAdding(false)}>
              Cancelar
            </Button>
            <Button type="submit" size="sm" disabled={!draft.description.trim()} loading={create.isPending}>
              Guardar alerta
            </Button>
          </div>
        </form>
      )}

      {active.length === 0 && !adding ? (
        <EmptyState title="Sin alertas activas." />
      ) : (
        <ul className="space-y-2 p-4">
          {active.map((alert) => (
            <AlertRow key={alert.id} alert={alert} canManage={canManage} onToggle={() => toggle.mutate(alert)} />
          ))}
        </ul>
      )}

      {inactive.length > 0 && (
        <details className="border-t border-gray-100 px-5 py-3 dark:border-gray-800">
          <summary className="cursor-pointer text-theme-xs font-medium text-gray-500 hover:text-gray-800 dark:hover:text-gray-200">Inactivas ({inactive.length})</summary>
          <ul className="mt-2 space-y-2">
            {inactive.map((alert) => (
              <AlertRow key={alert.id} alert={alert} canManage={canManage} onToggle={() => toggle.mutate(alert)} />
            ))}
          </ul>
        </details>
      )}
    </Card>
  );
}

function AlertRow({ alert, canManage, onToggle }: { alert: PetAlert; canManage: boolean; onToggle: () => void }) {
  const tone = !alert.is_active
    ? "bg-gray-50 text-gray-500 dark:bg-white/3 dark:text-gray-400"
    : alert.type.value === "behavior"
      ? "bg-warning-50 text-warning-800 dark:bg-warning-500/10 dark:text-warning-300"
      : "bg-error-50 text-error-800 dark:bg-error-500/10 dark:text-error-300";

  return (
    <li className={cn("flex items-start gap-3 rounded-lg px-3 py-2.5 text-theme-sm", tone)}>
      <div className="min-w-0 flex-1">
        <p>
          <span className="font-semibold">{alert.type.label}:</span> {alert.description}
        </p>
        <p className="mt-0.5 text-theme-xs opacity-75">Importancia {alert.severity.label.toLowerCase()}</p>
      </div>
      {canManage && (
        <button type="button" onClick={onToggle} className="shrink-0 text-theme-xs font-medium underline-offset-2 opacity-80 hover:underline hover:opacity-100">
          {alert.is_active ? "Desactivar" : "Reactivar"}
        </button>
      )}
    </li>
  );
}
