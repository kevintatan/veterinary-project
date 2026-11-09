import DocumentPaper from "@/components/domain/DocumentPaper";
import { type PortalDocument, type PortalMe, TUTOR_BASE } from "@/lib/portal";
import { portalApi } from "@/lib/server/portal";
import type { Metadata } from "next";
import PortalHeader from "../../PortalHeader";
import PortalSign from "./PortalSign";

export const dynamic = "force-dynamic";
export const metadata: Metadata = { title: "Documento" };

/** A consent in the portal: read it and sign (the session already identifies the owner), or download it signed. */
export default async function Page({ params }: { params: Promise<{ id: string }> }) {
  const { id } = await params;
  const [me, doc] = await Promise.all([portalApi<PortalMe>("me"), portalApi<PortalDocument>(`documents/${id}`)]);
  const s = doc.snapshot;

  return (
    <>
      <PortalHeader name={me.name} back={{ href: "/portal", label: "Inicio" }} />
      <main className="mx-auto max-w-xl space-y-4 px-4 py-6">
        <DocumentPaper
          clinic={s.clinic}
          title={s.title}
          date={s.date}
          facts={[
            ["Paciente", [s.pet.name, s.pet.species, s.pet.breed].filter(Boolean).join(" · ")],
            ["Sexo · Edad · Peso", [s.pet.sex, s.pet.age, s.pet.weight].filter(Boolean).join(" · ")],
            ["Tutor", s.owner.name],
            ["Médico veterinario", `${s.vet.name} · T.P. ${s.vet.license}`],
          ]}
          blocks={s.blocks}
        />
        {doc.to_sign ? (
          <PortalSign id={doc.id} />
        ) : doc.status === "signed" ? (
          <a
            href={`${TUTOR_BASE}/portal/documents/${doc.id}/pdf`}
            target="_blank"
            rel="noreferrer"
            className="block w-full rounded-xl bg-brand-500 px-4 py-3.5 text-center text-sm font-semibold text-white hover:bg-brand-600"
          >
            Descargar PDF firmado
          </a>
        ) : (
          <p className="rounded-xl bg-gray-100 px-4 py-3 text-center text-theme-sm text-gray-600 dark:bg-white/5 dark:text-gray-300">Ya firmaste. Falta la firma de la clínica.</p>
        )}
      </main>
    </>
  );
}
