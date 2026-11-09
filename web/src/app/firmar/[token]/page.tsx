import { Logo } from "@/components/brand/Logo";
import { laravel, visitorHeaders } from "@/lib/server/laravel";
import type { DocumentBlock } from "@/lib/documents";
import type { Metadata } from "next";
import { notFound } from "next/navigation";
import RemoteSign from "./RemoteSign";

// Personal and clinical data: never indexed or cached.
export const metadata: Metadata = { title: "Firmar documento", robots: { index: false, follow: false } };
export const dynamic = "force-dynamic";

export type RemoteDocument = {
  title: string;
  date: string;
  clinic: { name: string; tax_id: string | null; address: string | null; phone: string | null };
  owner_first_name: string;
  owner_name: string;
  pet: { name: string; species: string | null; breed: string; sex: string | null; age: string; weight: string };
  vet: { name: string; license: string };
  blocks: DocumentBlock[];
  expires_at: string;
  attempts_left: number;
};

/**
 * The page behind the link the clinic sends to sign a consent remotely (D3):
 * read it, confirm the last 4 ID digits, sign with the finger.
 */
export default async function Page({ params }: { params: Promise<{ token: string }> }) {
  const { token } = await params;
  if (!/^[A-Za-z0-9]{48}$/.test(token)) notFound();

  const { status, data } = await laravel<{ data?: RemoteDocument; message?: string }>(`public/sign/${token}`, { headers: await visitorHeaders() });

  return (
    <main className="min-h-screen bg-gray-50 px-4 py-6 text-gray-900 dark:bg-gray-950 dark:text-white">
      <div className="mx-auto max-w-xl space-y-4">
        <Logo className="mx-auto h-7 w-auto" />
        {status !== 200 || !data.data ? (
          <section className="rounded-2xl border border-gray-200 bg-white p-6 text-center dark:border-gray-800 dark:bg-gray-900">
            <h1 className="text-lg font-semibold">Este enlace no está disponible</h1>
            <p className="mt-2 text-theme-sm text-gray-500">{data.message ?? "Revisa que el enlace esté completo o pídele uno nuevo a la clínica."}</p>
          </section>
        ) : (
          <RemoteSign token={token} doc={data.data} />
        )}
      </div>
    </main>
  );
}
