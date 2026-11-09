"use client";

import { Card, CardHeader, EmptyState, Skeleton } from "@/components/common/Card";
import PetAvatar from "@/components/domain/PetAvatar";
import { useCan } from "@/components/providers/SessionProvider";
import Button from "@/components/ui/button/Button";
import { api, ApiError } from "@/lib/api";
import { type Consents, formatPhone, initials, type Owner, petSummary, whatsappLink } from "@/lib/patients";
import { cn } from "@/utils";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import Link from "next/link";
import { useRouter, useSearchParams } from "next/navigation";
import { useState } from "react";
import PetFormPanel from "../../mascotas/PetFormPanel";
import OwnerFormPanel from "../OwnerFormPanel";
import AccountCard from "./AccountCard";
import MessagesCard from "./MessagesCard";
import PortalCard from "./PortalCard";

export default function OwnerProfile({ id }: { id: string }) {
  const router = useRouter();
  const params = useSearchParams();
  const canManage = useCan("patients.manage");
  const justCreated = params.get("nuevo") === "1";

  const [editing, setEditing] = useState(false);
  const [addingPet, setAddingPet] = useState(justCreated);

  const owner = useQuery({
    queryKey: ["owners", id],
    queryFn: () => api<{ data: Owner }>(`owners/${id}`).then((r) => r.data),
  });

  if (owner.isLoading) {
    return (
      <div className="space-y-6">
        <Skeleton className="h-36" />
        <Skeleton className="h-64" />
      </div>
    );
  }

  if (owner.isError || !owner.data) {
    const notFound = owner.error instanceof ApiError && owner.error.status === 404;
    return (
      <Card>
        <EmptyState title={notFound ? "Este tutor no existe o fue eliminado." : "No fue posible cargar el tutor."} />
        <div className="pb-8 text-center">
          <Link href="/app/tutores" className="text-theme-sm font-medium text-brand-600 hover:underline">
            Volver a tutores
          </Link>
        </div>
      </Card>
    );
  }

  const o = owner.data;
  const alive = o.pets.filter((p) => !p.deceased_at);
  const deceased = o.pets.filter((p) => p.deceased_at);

  return (
    <>
      <nav aria-label="Ruta" className="mb-4 text-theme-sm text-gray-500">
        <Link href="/app/tutores" className="hover:text-gray-800 dark:hover:text-gray-200">
          Tutores
        </Link>
        <span className="mx-2 text-gray-300">/</span>
        <span className="text-gray-800 dark:text-white/90">{o.full_name}</span>
      </nav>

      {justCreated && alive.length === 0 && (
        <div className="mb-6 rounded-xl border border-brand-200 bg-brand-50 px-4 py-3 text-theme-sm text-brand-800 dark:border-brand-500/30 dark:bg-brand-500/10 dark:text-brand-300">
          Tutor registrado. Ahora registra su mascota para poder agendarle citas.
        </div>
      )}

      {/* Header */}
      <Card className="mb-6 p-5 sm:p-6">
        <div className="flex flex-wrap items-start gap-5">
          <span className="flex h-16 w-16 shrink-0 items-center justify-center rounded-2xl bg-gray-100 text-lg font-semibold text-gray-600 dark:bg-white/5 dark:text-gray-300">
            {initials(o.full_name)}
          </span>
          <div className="min-w-0 flex-1">
            <h1 className="text-title-sm font-semibold text-gray-900 dark:text-white">{o.full_name}</h1>
            <p className="mt-1 text-theme-sm text-gray-500 dark:text-gray-400">
              {o.document_number ? `${o.document_type?.label ?? ""} ${o.document_number}` : "Sin documento registrado"}
            </p>
          </div>
          <div className="flex flex-wrap gap-2">
            {o.mobile_phone && (
              <>
                <a href={whatsappLink(o.mobile_phone)} target="_blank" rel="noopener noreferrer" className="inline-flex items-center gap-2 rounded-lg border border-gray-200 px-3.5 py-2 text-theme-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/5">
                  <span className="text-[#1f9d55]">●</span> WhatsApp
                </a>
                <a href={`tel:${o.mobile_phone}`} className="inline-flex items-center rounded-lg border border-gray-200 px-3.5 py-2 text-theme-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/5">
                  Llamar
                </a>
              </>
            )}
            {canManage && (
              <Button size="sm" variant="outline" onClick={() => setEditing(true)}>
                Editar
              </Button>
            )}
          </div>
        </div>

        <dl className="mt-6 grid gap-x-8 gap-y-4 border-t border-gray-100 pt-5 text-theme-sm sm:grid-cols-2 lg:grid-cols-4 dark:border-gray-800">
          <Detail term="Celular">{formatPhone(o.mobile_phone)}</Detail>
          <Detail term="Correo">{o.email}</Detail>
          <Detail term="Dirección">{[o.address, o.neighborhood].filter(Boolean).join(" · ")}</Detail>
          <Detail term="Contacto alterno">{[o.alt_contact_name, formatPhone(o.alt_contact_phone)].filter(Boolean).join(" · ")}</Detail>
        </dl>

        {o.important_notes && (
          <p className="mt-5 rounded-lg bg-warning-50 px-3 py-2.5 text-theme-sm text-warning-800 dark:bg-warning-500/10 dark:text-warning-300">
            <span className="font-semibold">Nota: </span>
            {o.important_notes}
          </p>
        )}
      </Card>

      <div className="grid gap-6 xl:grid-cols-3">
        {/* Pets */}
        <section className="xl:col-span-2" aria-labelledby="pets-title">
          <div className="mb-3 flex items-center justify-between">
            <h2 id="pets-title" className="text-base font-semibold text-gray-800 dark:text-white/90">
              Mascotas <span className="font-normal text-gray-400">({alive.length})</span>
            </h2>
            {canManage && (
              <Button size="sm" onClick={() => setAddingPet(true)}>
                Agregar mascota
              </Button>
            )}
          </div>

          {o.pets.length === 0 ? (
            <Card>
              <EmptyState title="Aún no tiene mascotas registradas." />
            </Card>
          ) : (
            <div className="grid gap-4 sm:grid-cols-2">
              {[...alive, ...deceased].map((pet) => (
                <Link
                  key={pet.id}
                  href={`/app/mascotas/${pet.id}`}
                  className={cn(
                    "group flex gap-4 rounded-2xl border border-gray-200 bg-white p-4 transition hover:border-brand-300 hover:shadow-theme-sm dark:border-gray-800 dark:bg-white/3 dark:hover:border-brand-500/50",
                    pet.deceased_at && "opacity-60",
                  )}
                >
                  <PetAvatar photoUrl={pet.photo_url} speciesCode={pet.species?.code} name={pet.name} />
                  <div className="min-w-0 flex-1">
                    <p className="font-semibold text-gray-900 group-hover:text-brand-700 dark:text-white dark:group-hover:text-brand-300">
                      {pet.name}
                      {pet.deceased_at && <span className="ms-2 text-theme-xs font-normal text-gray-500">Fallecido</span>}
                    </p>
                    <p className="mt-0.5 text-theme-xs text-gray-500 dark:text-gray-400">{petSummary(pet)}</p>
                    {(pet.alerts?.length ?? 0) > 0 && (
                      <div className="mt-2 flex flex-wrap gap-1">
                        {pet.alerts!.map((a) => (
                          <span
                            key={a.id}
                            title={a.description}
                            className={cn(
                              "rounded-full px-2 py-0.5 text-[11px] font-medium",
                              a.type.value === "behavior"
                                ? "bg-warning-50 text-warning-700 dark:bg-warning-500/15 dark:text-warning-400"
                                : "bg-error-50 text-error-700 dark:bg-error-500/15 dark:text-error-400",
                            )}
                          >
                            {a.type.label}
                          </span>
                        ))}
                      </div>
                    )}
                  </div>
                </Link>
              ))}
            </div>
          )}
        </section>

        <div className="space-y-6">
          <ConsentsCard owner={o} canManage={canManage} />
          <AccountCard owner={o} />
          <PortalCard ownerId={o.id} />
          <MessagesCard ownerId={o.id} phone={o.mobile_phone} />
        </div>
      </div>

      {editing && <OwnerFormPanel owner={o} onClose={() => setEditing(false)} />}
      {addingPet && (
        <PetFormPanel
          ownerId={o.id}
          ownerName={o.full_name}
          onClose={() => {
            setAddingPet(false);
            if (justCreated) router.replace(`/app/tutores/${o.id}`, { scroll: false });
          }}
          onSaved={(pet) => router.push(`/app/mascotas/${pet.id}`)}
        />
      )}
    </>
  );
}

function Detail({ term, children }: { term: string; children: React.ReactNode }) {
  return (
    <div className="min-w-0">
      <dt className="text-theme-xs text-gray-500 dark:text-gray-400">{term}</dt>
      <dd className="mt-0.5 truncate text-gray-800 dark:text-white/90">{children || <span className="text-gray-400">—</span>}</dd>
    </div>
  );
}

const CONSENT_ROWS: { key: keyof Consents; label: string; description: string }[] = [
  { key: "data_processing", label: "Tratamiento de datos", description: "Habeas Data (Ley 1581). Obligatoria para atenderlo." },
  { key: "whatsapp", label: "Recordatorios por WhatsApp", description: "Citas, vacunas y controles." },
  { key: "sms", label: "Recordatorios por SMS", description: "Si no usa WhatsApp." },
  { key: "email", label: "Correo electrónico", description: "Resúmenes y comprobantes." },
  { key: "marketing", label: "Promociones", description: "Campañas y novedades de la clínica." },
];

/** Each change is recorded with date, user and channel (audit trail of consent). */
function ConsentsCard({ owner, canManage }: { owner: Owner; canManage: boolean }) {
  const queryClient = useQueryClient();
  const consents = owner.consents;

  const update = useMutation({
    mutationFn: (change: Partial<Consents>) => api<{ data: Consents }>(`owners/${owner.id}/consents`, { method: "PUT", body: change }),
    onSuccess: ({ data }) => queryClient.setQueryData<Owner>(["owners", owner.id], (o) => (o ? { ...o, consents: data } : o)),
  });

  const error = update.error instanceof ApiError ? (update.error.field("consents") ?? update.error.message) : null;

  return (
    <Card className="h-fit">
      <CardHeader title="Autorizaciones" description="Cada cambio queda registrado con fecha y usuario." />
      <ul className="divide-y divide-gray-100 dark:divide-gray-800">
        {CONSENT_ROWS.map((row) => {
          const on = !!consents?.[row.key];
          return (
            <li key={row.key} className="flex items-center justify-between gap-4 px-5 py-3">
              <div>
                <p className="text-theme-sm font-medium text-gray-800 dark:text-white/90">{row.label}</p>
                <p className="text-theme-xs text-gray-500 dark:text-gray-400">{row.description}</p>
              </div>
              <button
                type="button"
                role="switch"
                aria-checked={on}
                aria-label={row.label}
                disabled={!canManage || update.isPending}
                onClick={() => update.mutate({ [row.key]: !on })}
                className={cn(
                  "relative h-6 w-11 shrink-0 rounded-full transition disabled:cursor-not-allowed disabled:opacity-60",
                  on ? "bg-brand-500" : "bg-gray-200 dark:bg-gray-700",
                )}
              >
                <span className={cn("absolute top-0.5 left-0.5 h-5 w-5 rounded-full bg-white shadow-theme-xs transition", on && "translate-x-5")} />
              </button>
            </li>
          );
        })}
      </ul>
      {error && <p className="px-5 pb-4 text-theme-xs text-error-500">{error}</p>}
    </Card>
  );
}
