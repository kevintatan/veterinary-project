import { LogoMark } from "@/components/brand/Logo";
import { laravel } from "@/lib/server/laravel";
import { cn } from "@/utils";
import type { Metadata } from "next";
import { headers } from "next/headers";
import { notFound } from "next/navigation";

type PublicCard = {
  pet: { name: string; species: string | null; breed: string | null; sex: string | null; color: string | null; age: string | null; photo_url: string | null; microchip: boolean; deceased: boolean };
  clinic: { name: string; phone: string | null; address: string | null };
  vaccinations: { vaccine: string; applied_on: string; valid_until: string | null; status: "valid" | "expired" | "no_booster"; label: string }[];
  lost: { since: string; owner_first_name: string; contact_phone: string | null } | null;
};

// Personal data of a pet: never indexed by search engines nor cached by the CDN.
export const metadata: Metadata = { title: "Carné de mascota", robots: { index: false, follow: false } };
export const dynamic = "force-dynamic";

const formatDate = (day: string) =>
  new Intl.DateTimeFormat("es-CO", { day: "numeric", month: "short", year: "numeric", timeZone: "UTC" }).format(new Date(`${day}T00:00:00Z`)).replace(/\./g, "");

/**
 * The page behind the QR of a pet card (docs/database.md §10b). Read on a phone,
 * by a dog daycare, a groomer, a building guard… or whoever found a lost pet.
 */
export default async function Page({ params }: { params: Promise<{ token: string }> }) {
  const { token } = await params;
  if (!/^[A-Za-z0-9]{32}$/.test(token)) notFound();

  // Forward the visitor (not this server) so scans are counted per person.
  const incoming = await headers();
  const forwarded: Record<string, string> = {};
  const ip = incoming.get("x-forwarded-for") ?? incoming.get("x-real-ip");
  if (ip) forwarded["X-Forwarded-For"] = ip;
  const ua = incoming.get("user-agent");
  if (ua) forwarded["User-Agent"] = ua;

  const { status, data } = await laravel<{ data?: PublicCard }>(`public/pets/${token}`, { headers: forwarded });
  if (status !== 200 || !data.data) notFound();

  const { pet, clinic, vaccinations, lost } = data.data;
  const phoneDigits = lost?.contact_phone?.replace(/\D/g, "");
  const found = encodeURIComponent(`Hola ${lost?.owner_first_name ?? ""}, encontré a ${pet.name}. Te escribo por su carné de Zooetia.`);

  return (
    <main className="min-h-screen bg-gray-50 px-4 py-6 text-gray-900 dark:bg-gray-950 dark:text-white">
      <div className="mx-auto max-w-md space-y-4">
        {lost && (
          <section className="overflow-hidden rounded-2xl bg-accent-500 text-white shadow-theme-lg">
            <div className="px-5 py-4">
              <p className="text-theme-xs font-semibold tracking-wider uppercase opacity-90">Mascota perdida</p>
              <p className="mt-1 text-xl font-bold">¡Hola! Soy {pet.name} y me perdí.</p>
              <p className="mt-1 text-theme-sm opacity-95">Por favor avísale a {lost.owner_first_name}. Gracias por ayudarme a volver a casa.</p>
            </div>
            {phoneDigits && (
              <div className="grid grid-cols-2 gap-px bg-white/25">
                <a href={`tel:+${phoneDigits}`} className="bg-accent-600 py-3.5 text-center text-sm font-semibold hover:bg-accent-700">
                  Llamar
                </a>
                <a href={`https://wa.me/${phoneDigits}?text=${found}`} className="bg-accent-600 py-3.5 text-center text-sm font-semibold hover:bg-accent-700">
                  WhatsApp
                </a>
              </div>
            )}
          </section>
        )}

        <section className="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900">
          <div className="flex items-center gap-4">
            {pet.photo_url ? (
              // eslint-disable-next-line @next/next/no-img-element -- streamed by our own public route
              <img src={`/api/public/pet-photo/${token}`} alt={`Foto de ${pet.name}`} className="h-24 w-24 shrink-0 rounded-3xl object-cover" />
            ) : (
              <span className="flex h-24 w-24 shrink-0 items-center justify-center rounded-3xl bg-brand-50 dark:bg-brand-500/15">
                <LogoMark className="h-10 w-10" />
              </span>
            )}
            <div className="min-w-0">
              <h1 className="text-2xl font-bold">{pet.name}</h1>
              <p className="text-theme-sm text-gray-500 dark:text-gray-400">{[pet.species, pet.breed, pet.sex, pet.age].filter(Boolean).join(" · ")}</p>
              {pet.color && <p className="text-theme-xs text-gray-500">Color: {pet.color}</p>}
              {pet.microchip && <p className="mt-1 text-theme-xs font-medium text-brand-700 dark:text-brand-300">Tiene microchip</p>}
            </div>
          </div>
          {pet.deceased && <p className="mt-4 rounded-lg bg-gray-100 px-3 py-2 text-theme-sm text-gray-600 dark:bg-white/5 dark:text-gray-400">En memoria.</p>}
        </section>

        <section className="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
          <header className="border-b border-gray-100 px-5 py-4 dark:border-gray-800">
            <h2 className="font-semibold">Vacunas</h2>
            <p className="text-theme-xs text-gray-500">Registradas y verificadas por {clinic.name}.</p>
          </header>
          {vaccinations.length === 0 ? (
            <p className="px-5 py-6 text-theme-sm text-gray-500">Aún no hay vacunas registradas.</p>
          ) : (
            <ul className="divide-y divide-gray-100 dark:divide-gray-800">
              {vaccinations.map((v) => (
                <li key={v.vaccine} className="flex items-center gap-3 px-5 py-3">
                  <span
                    className={cn(
                      "flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-sm font-bold",
                      v.status === "valid" && "bg-success-50 text-success-700 dark:bg-success-500/15 dark:text-success-400",
                      v.status === "expired" && "bg-error-50 text-error-700 dark:bg-error-500/15 dark:text-error-400",
                      v.status === "no_booster" && "bg-gray-100 text-gray-500 dark:bg-white/5",
                    )}
                    aria-hidden
                  >
                    {v.status === "valid" ? "✓" : v.status === "expired" ? "!" : "·"}
                  </span>
                  <div className="min-w-0 flex-1">
                    <p className="text-theme-sm font-medium">{v.vaccine}</p>
                    <p className="text-theme-xs text-gray-500">Aplicada el {formatDate(v.applied_on)}</p>
                  </div>
                  <div className="text-right">
                    <p
                      className={cn(
                        "text-theme-xs font-semibold",
                        v.status === "valid" ? "text-success-700 dark:text-success-400" : v.status === "expired" ? "text-error-700 dark:text-error-400" : "text-gray-500",
                      )}
                    >
                      {v.label}
                    </p>
                    {v.valid_until && <p className="text-theme-xs text-gray-500">hasta {formatDate(v.valid_until)}</p>}
                  </div>
                </li>
              ))}
            </ul>
          )}
        </section>

        <section className="rounded-2xl border border-gray-200 bg-white px-5 py-4 text-theme-sm dark:border-gray-800 dark:bg-gray-900">
          <p className="font-semibold">{clinic.name}</p>
          {clinic.address && <p className="text-gray-500">{clinic.address}</p>}
          {clinic.phone && (
            <a href={`tel:${clinic.phone.replace(/[^\d+]/g, "")}`} className="font-medium text-brand-700 dark:text-brand-300">
              {clinic.phone}
            </a>
          )}
        </section>

        <p className="pt-2 text-center text-theme-xs text-gray-400">
          Carné digital de <span className="font-semibold text-gray-500">Zooetia</span>. Solo se muestran datos que la clínica verifica; nunca documentos ni la historia clínica.
        </p>
      </div>
    </main>
  );
}
