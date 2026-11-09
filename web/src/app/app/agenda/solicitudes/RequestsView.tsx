"use client";

import { Card, EmptyState, Skeleton } from "@/components/common/Card";
import PageHeader from "@/components/common/PageHeader";
import { useCan, useSession } from "@/components/providers/SessionProvider";
import Button from "@/components/ui/button/Button";
import { api, ApiError } from "@/lib/api";
import { bookingKeys, type BookingRequest } from "@/lib/booking";
import { formatPhone, whatsappLink } from "@/lib/patients";
import { cn } from "@/utils";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import Link from "next/link";
import { useState } from "react";

const MATCH = { both: "mismo documento y celular", document: "mismo documento", phone: "mismo celular" } as const;

const QUICK_REASONS = ["No tenemos disponibilidad a esa hora", "Ese servicio requiere una valoración previa", "Te contactaremos para acordar otra hora"];

/**
 * Online booking requests waiting for the clinic (docs/reservas.md).
 * Accept = link to an existing tutor/pet (suggested by phone) or create them.
 */
export default function RequestsView() {
  const { clinic } = useSession();
  const canManage = useCan("appointments.manage");
  const requests = useQuery({
    queryKey: bookingKeys.list,
    queryFn: () => api<{ data: BookingRequest[] }>("booking-requests").then((r) => r.data),
    refetchInterval: 60_000,
  });

  return (
    <>
      <PageHeader
        title="Solicitudes de cita en línea"
        subtitle="Las personas piden la cita desde el enlace de reservas; la cita queda apartada hasta que la aceptes o la rechaces."
        actions={
          <Link href="/app/agenda" className="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300">
            Volver a la agenda
          </Link>
        }
      />
      {requests.isLoading ? (
        <Skeleton className="h-60" />
      ) : !requests.data?.length ? (
        <Card>
          <EmptyState title="No hay solicitudes pendientes" description="Cuando alguien pida una cita desde el enlace de reservas, aparecerá aquí y en la campana." />
        </Card>
      ) : (
        <div className="space-y-4">
          {requests.data.map((r) => (
            <RequestCard key={r.id} request={r} timeZone={clinic.timezone} canManage={canManage} />
          ))}
        </div>
      )}
    </>
  );
}

type Choice = { key: string; owner_id?: string; pet_id?: string; label: string };

function RequestCard({ request: r, timeZone, canManage }: { request: BookingRequest; timeZone: string; canManage: boolean }) {
  const queryClient = useQueryClient();
  const d = r.details;

  // Options: each suggested tutor's pets, the tutor with a new pet, or a new tutor.
  const choices: Choice[] = [
    ...r.suggestions.flatMap((o) => [
      ...o.pets.map((p) => ({ key: `${o.id}:${p.id}`, owner_id: o.id, pet_id: p.id, label: `${o.full_name} · ${p.name}${p.same_name ? " (mismo nombre)" : ""}` })),
      { key: `${o.id}:new`, owner_id: o.id, label: `${o.full_name} · mascota nueva “${d.pet_name}”` },
    ]),
    // Same document = same person: no new tutor (the API refuses duplicates too).
    ...(r.suggestions.some((o) => o.match !== "phone") ? [] : [{ key: "new", label: `Tutor nuevo “${d.name}” con mascota nueva “${d.pet_name}”` }]),
  ];
  const preferred = r.suggestions.flatMap((o) => o.pets.filter((p) => p.same_name).map((p) => `${o.id}:${p.id}`))[0] ?? (r.suggestions[0] ? `${r.suggestions[0].id}:new` : "new");
  const [choice, setChoice] = useState(preferred);
  const [rejecting, setRejecting] = useState(false);
  const [updatePhone, setUpdatePhone] = useState(true);
  const chosenOwner = r.suggestions.find((o) => choice.startsWith(`${o.id}:`));
  const phoneDiffers = !!chosenOwner && chosenOwner.mobile_phone !== d.phone;
  const [reason, setReason] = useState("");

  const done = () => {
    queryClient.invalidateQueries({ queryKey: bookingKeys.all });
    queryClient.invalidateQueries({ queryKey: ["agenda"] });
    queryClient.invalidateQueries({ queryKey: ["notifications"] });
  };
  const accept = useMutation({
    mutationFn: () => {
      const c = choices.find((x) => x.key === choice)!;
      return api(`booking-requests/${r.id}/accept`, { method: "POST", body: { owner_id: c.owner_id ?? null, pet_id: c.pet_id ?? null, update_phone: phoneDiffers && updatePhone } });
    },
    onSuccess: done,
  });
  const reject = useMutation({
    mutationFn: () => api(`booking-requests/${r.id}/reject`, { method: "POST", body: { reason } }),
    onSuccess: done,
  });
  const error = (accept.error ?? reject.error) as ApiError | null;

  const start = new Date(r.starts_at);
  const day = start.toLocaleDateString("es-CO", { weekday: "long", day: "numeric", month: "long", timeZone });
  const time = start.toLocaleTimeString("es-CO", { hour: "numeric", minute: "2-digit", timeZone });

  return (
    <Card className={cn(r.is_past && "opacity-70")}>
      <div className="grid gap-5 p-5 lg:grid-cols-[220px_1fr_300px]">
        <div>
          <p className="text-theme-xs font-semibold tracking-wide text-[#6938ef] uppercase">Solicitud en línea</p>
          <p className="mt-1 text-lg font-semibold text-gray-900 first-letter:uppercase dark:text-white">{day}</p>
          <p className="text-2xl font-semibold text-gray-900 tabular dark:text-white">{time}</p>
          <p className="mt-2 text-theme-sm text-gray-600 dark:text-gray-300">{r.service?.name}</p>
          <p className="text-theme-xs text-gray-500">
            {r.professional?.name}
            {d.professional_chosen ? " (elegido por el tutor)" : ""} · {r.branch?.name}
          </p>
          {r.is_past && <p className="mt-2 text-theme-xs font-medium text-error-600">La hora ya pasó: recházala para liberarla.</p>}
        </div>

        <div className="min-w-0 border-gray-100 lg:border-x lg:px-5 dark:border-gray-800">
          <p className="text-base font-semibold text-gray-900 dark:text-white">
            {d.pet_name} <span className="text-sm font-normal text-gray-500">· {d.species_name}</span>
          </p>
          <p className="mt-1 text-theme-sm text-gray-700 dark:text-gray-300">
            {d.name}
            {d.document_number && <span className="text-gray-500"> · {d.document_type} {d.document_number}</span>} ·{" "}
            <a href={whatsappLink(d.phone)} target="_blank" rel="noreferrer" className="font-medium text-brand-600 hover:underline">
              {formatPhone(d.phone)}
            </a>
          </p>
          {d.notes && <p className="mt-2 rounded-lg bg-gray-50 px-3 py-2 text-theme-sm text-gray-700 italic dark:bg-white/5 dark:text-gray-300">“{d.notes}”</p>}
          <p className="mt-2 text-theme-xs text-gray-400">
            Pedida el {new Date(d.requested_at).toLocaleString("es-CO", { day: "numeric", month: "short", hour: "numeric", minute: "2-digit", timeZone })} · Autorizó el tratamiento de datos y
            WhatsApp.
          </p>

          {canManage && (
            <fieldset className="mt-4">
              <legend className="mb-2 text-theme-xs font-semibold text-gray-500">
                {r.suggestions.length ? "Ya hay un tutor con estos datos. ¿A quién corresponde?" : "Al aceptar se crea:"}
              </legend>
              {r.suggestions.map((o) => (
                <p key={o.id} className="mb-2 text-theme-xs text-gray-500">
                  <b className="text-gray-700 dark:text-gray-300">{o.full_name}</b>: {MATCH[o.match]}
                  {o.match === "document" && o.mobile_phone && <span className="text-warning-700"> · celular registrado distinto ({formatPhone(o.mobile_phone)})</span>}
                </p>
              ))}
              <div className="space-y-1.5">
                {choices.map((c) => (
                  <label key={c.key} className={cn("flex cursor-pointer items-center gap-2.5 rounded-lg border px-3 py-2 text-theme-sm", choice === c.key ? "border-brand-500 bg-brand-25 dark:bg-brand-500/10" : "border-gray-200 dark:border-gray-700")}>
                    <input type="radio" name={`choice-${r.id}`} checked={choice === c.key} onChange={() => setChoice(c.key)} className="accent-brand-500" />
                    <span className="text-gray-800 dark:text-gray-200">{c.label}</span>
                  </label>
                ))}
              </div>
              {phoneDiffers && (
                <label className="mt-3 flex cursor-pointer items-start gap-2.5 rounded-lg bg-warning-50 px-3 py-2 text-theme-sm text-warning-800 dark:bg-warning-500/10 dark:text-warning-300">
                  <input type="checkbox" checked={updatePhone} onChange={(e) => setUpdatePhone(e.target.checked)} className="mt-0.5 accent-brand-500" />
                  <span>
                    Actualizar su celular a <b>{formatPhone(d.phone)}</b>
                    {chosenOwner?.mobile_phone ? ` (hoy tiene ${formatPhone(chosenOwner.mobile_phone)})` : ""}. Los recordatorios llegan a ese número.
                  </span>
                </label>
              )}
            </fieldset>
          )}
        </div>

        {canManage && (
          <div className="flex flex-col gap-2">
            {!rejecting ? (
              <>
                <Button onClick={() => accept.mutate()} loading={accept.isPending} disabled={r.is_past}>
                  Aceptar cita
                </Button>
                <Button variant="outline" onClick={() => setRejecting(true)}>
                  Rechazar
                </Button>
                <p className="mt-1 text-theme-xs text-gray-500">Al aceptarla queda programada, le avisamos por WhatsApp y recibe los recordatorios de siempre.</p>
              </>
            ) : (
              <>
                <label htmlFor={`reason-${r.id}`} className="text-theme-xs font-semibold text-gray-600 dark:text-gray-300">
                  Motivo (se le enviará por WhatsApp)
                </label>
                <div className="flex flex-wrap gap-1.5">
                  {QUICK_REASONS.map((q) => (
                    <button key={q} type="button" onClick={() => setReason(q)} className="rounded-full border border-gray-200 px-2.5 py-1 text-theme-xs text-gray-600 hover:border-brand-300 dark:border-gray-700 dark:text-gray-300">
                      {q}
                    </button>
                  ))}
                </div>
                <textarea id={`reason-${r.id}`} value={reason} onChange={(e) => setReason(e.target.value)} rows={3} maxLength={255} className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-900" />
                <div className="flex gap-2">
                  <Button variant="danger" className="flex-1" onClick={() => reject.mutate()} loading={reject.isPending} disabled={!reason.trim()}>
                    Rechazar solicitud
                  </Button>
                  <Button variant="ghost" onClick={() => setRejecting(false)}>
                    Volver
                  </Button>
                </div>
              </>
            )}
            {error && <p className="text-theme-xs text-error-600">{error.message}</p>}
          </div>
        )}
      </div>
    </Card>
  );
}
