import BookingFlow, { type BookingInfo } from "@/app/reservar/[slug]/BookingFlow";
import type { PortalBookingClinic } from "@/lib/portal";
import { laravel } from "@/lib/server/laravel";
import { portalApi } from "@/lib/server/portal";
import type { Metadata } from "next";
import Link from "next/link";
import PortalHeader from "../PortalHeader";

export const metadata: Metadata = { title: "Pedir una cita" };
export const dynamic = "force-dynamic";

type Props = { searchParams: Promise<{ clinica?: string; mascota?: string }> };

/**
 * "Pedir una cita" from the owner portal (docs/reservas.md, B3): the same
 * booking form as the clinic's public page, with the tutor's data and pets
 * already filled in. It is still a request the clinic accepts or rejects.
 */
export default async function Page({ searchParams }: Props) {
  const { clinica, mascota } = await searchParams;
  const [me, clinics] = await Promise.all([portalApi<{ name: string }>("me"), portalApi<PortalBookingClinic[]>("booking")]);

  // The clinic of the chosen pet, the one in the URL, or the only one.
  const chosen = clinics.find((c) => c.slug === clinica) ?? clinics.find((c) => c.pets.some((p) => p.id === mascota)) ?? (clinics.length === 1 ? clinics[0] : undefined);

  if (!chosen) {
    return (
      <>
        <PortalHeader name={me.name} />
        <main className="mx-auto max-w-md space-y-4 px-4 py-6">
          <h1 className="text-xl font-semibold">Pedir una cita</h1>
          {clinics.length === 0 ? (
            <p className="rounded-2xl border border-gray-200 bg-white p-6 text-center text-theme-sm text-gray-500 dark:border-gray-800 dark:bg-gray-900">
              Tu veterinaria todavía no recibe solicitudes de cita en línea. Escríbele o llámala para agendar.
            </p>
          ) : (
            <ul className="space-y-3">
              {clinics.map((c) => (
                <li key={c.slug}>
                  <Link href={`/portal/agendar?clinica=${c.slug}`} className="block rounded-2xl border border-gray-200 bg-white p-4 font-medium shadow-theme-xs hover:border-brand-300 dark:border-gray-800 dark:bg-gray-900">
                    {c.name} <span className="text-theme-sm font-normal text-gray-500">· {c.pets.map((p) => p.name).join(", ")}</span>
                  </Link>
                </li>
              ))}
            </ul>
          )}
          <Link href="/portal" className="inline-block text-theme-sm font-medium text-brand-600">
            ← Volver
          </Link>
        </main>
      </>
    );
  }

  const { status, data } = await laravel<{ data: BookingInfo }>(`public/booking/${chosen.slug}`);
  if (status !== 200) throw new Error(`public/booking/${chosen.slug}: ${status}`);

  return <BookingFlow info={data.data} prefill={{ ...chosen.prefill, pets: chosen.pets, petId: mascota ?? null }} backHref="/portal" />;
}
