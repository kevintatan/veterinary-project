/* eslint-disable @next/next/no-img-element -- private images behind the portal BFF */
import { appointmentWhen, type PortalBookingClinic, type PortalDocumentSummary, type PortalMe, type PortalPetSummary, TUTOR_BASE } from "@/lib/portal";
import { portalApi } from "@/lib/server/portal";
import Link from "next/link";
import PortalHeader from "./PortalHeader";

export const dynamic = "force-dynamic";

/** Owner portal home: every pet, from every clinic that linked the owner. */
export default async function Page() {
  const [me, pets, documents, booking] = await Promise.all([
    portalApi<PortalMe>("me"),
    portalApi<PortalPetSummary[]>("pets"),
    portalApi<PortalDocumentSummary[]>("documents"),
    portalApi<PortalBookingClinic[]>("booking"),
  ]);
  const toSign = documents.filter((d) => d.to_sign);
  const signed = documents.filter((d) => !d.to_sign);
  const first = me.name.trim().split(" ")[0];

  return (
    <>
      <PortalHeader name={me.name} />
      <main className="mx-auto max-w-md space-y-4 px-4 py-6">
        <div>
          <h1 className="text-xl font-semibold">Hola, {first}</h1>
          <p className="text-theme-sm text-gray-500">Tus mascotas, sus carnés y tus próximas citas.</p>
        </div>

        {booking.length > 0 && (
          <Link href="/portal/agendar" className="flex items-center justify-between rounded-2xl bg-brand-500 px-5 py-4 text-white shadow-theme-sm transition hover:bg-brand-600">
            <span>
              <span className="block font-semibold">Pedir una cita</span>
              <span className="block text-theme-xs text-white/80">Elige el servicio, el día y la hora. La clínica te confirma por WhatsApp.</span>
            </span>
            <span aria-hidden className="text-xl">→</span>
          </Link>
        )}

        {toSign.length > 0 && (
          <section className="space-y-2 rounded-2xl border border-warning-200 bg-warning-50 p-4 dark:border-warning-500/30 dark:bg-warning-500/10">
            <h2 className="text-theme-sm font-semibold text-warning-800 dark:text-warning-300">
              {toSign.length === 1 ? "Tienes un documento por firmar" : `Tienes ${toSign.length} documentos por firmar`}
            </h2>
            {toSign.map((d) => (
              <Link key={d.id} href={`/portal/documentos/${d.id}`} className="flex items-center justify-between gap-3 rounded-xl bg-white px-4 py-3 shadow-theme-xs dark:bg-gray-900">
                <span className="min-w-0">
                  <span className="block truncate text-theme-sm font-medium">{d.title}</span>
                  <span className="block text-theme-xs text-gray-500">
                    {d.pet} · {d.clinic}
                  </span>
                </span>
                <span className="shrink-0 text-theme-sm font-semibold text-brand-600">Leer y firmar →</span>
              </Link>
            ))}
          </section>
        )}

        {pets.length === 0 ? (
          <p className="rounded-2xl border border-gray-200 bg-white p-6 text-center text-theme-sm text-gray-500 dark:border-gray-800 dark:bg-gray-900">
            Aún no hay mascotas en tu cuenta. Pídele a tu veterinaria que te envíe la invitación.
          </p>
        ) : (
          <ul className="space-y-3">
            {pets.map((pet) => (
              <li key={pet.id}>
                <Link
                  href={`/portal/mascotas/${pet.id}`}
                  className="flex gap-4 rounded-2xl border border-gray-200 bg-white p-4 shadow-theme-xs transition hover:border-brand-300 dark:border-gray-800 dark:bg-gray-900"
                >
                  {pet.photo_url ? (
                    <img src={`${TUTOR_BASE}/${pet.photo_url}`} alt="" className="size-16 shrink-0 rounded-xl object-cover" />
                  ) : (
                    <span className="flex size-16 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-2xl font-semibold text-brand-600 dark:bg-brand-500/10">
                      {pet.name.charAt(0)}
                    </span>
                  )}
                  <div className="min-w-0 flex-1">
                    <div className="flex items-center justify-between gap-2">
                      <p className="truncate font-semibold">{pet.name}</p>
                      <span className="shrink-0 text-theme-xs font-medium text-brand-600">Ver carné →</span>
                    </div>
                    <p className="truncate text-theme-xs text-gray-500">{[pet.species, pet.breed, pet.age].filter(Boolean).join(" · ")}</p>
                    <p className="truncate text-theme-xs text-gray-400">{pet.clinic.name}</p>
                    <div className="mt-2 flex flex-wrap gap-1.5">
                      {pet.deceased && <Chip tone="gray">En memoria</Chip>}
                      {pet.next_appointment && <Chip tone="brand">Cita: {appointmentWhen(pet.next_appointment)}</Chip>}
                      {pet.vaccines_expired > 0 && (
                        <Chip tone="warning">
                          {pet.vaccines_expired} {pet.vaccines_expired === 1 ? "vacuna vencida" : "vacunas vencidas"}
                        </Chip>
                      )}
                    </div>
                  </div>
                </Link>
              </li>
            ))}
          </ul>
        )}

        {signed.length > 0 && (
          <section className="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
            <h2 className="border-b border-gray-100 px-4 py-3 text-theme-sm font-semibold dark:border-gray-800">Documentos firmados</h2>
            <ul className="divide-y divide-gray-100 dark:divide-gray-800">
              {signed.map((d) => (
                <li key={d.id} className="flex items-center justify-between gap-3 px-4 py-3">
                  <span className="min-w-0">
                    <span className="block truncate text-theme-sm font-medium">{d.title}</span>
                    <span className="block text-theme-xs text-gray-500">
                      {d.pet} · {d.clinic}
                      {d.signed_at && ` · ${new Date(d.signed_at).toLocaleDateString("es-CO", { day: "numeric", month: "short", year: "numeric" }).replace(/\./g, "")}`}
                    </span>
                  </span>
                  <a href={`${TUTOR_BASE}/portal/documents/${d.id}/pdf`} target="_blank" rel="noreferrer" className="shrink-0 text-theme-sm font-semibold text-brand-600">
                    Descargar PDF
                  </a>
                </li>
              ))}
            </ul>
          </section>
        )}
      </main>
    </>
  );
}

function Chip({ tone, children }: { tone: "brand" | "warning" | "gray"; children: React.ReactNode }) {
  const tones = {
    brand: "bg-brand-50 text-brand-700 dark:bg-brand-500/10 dark:text-brand-300",
    warning: "bg-warning-50 text-warning-700 dark:bg-warning-500/10 dark:text-warning-300",
    gray: "bg-gray-100 text-gray-600 dark:bg-white/5 dark:text-gray-300",
  };
  return <span className={`rounded-full px-2 py-0.5 text-[11px] font-medium ${tones[tone]}`}>{children}</span>;
}
