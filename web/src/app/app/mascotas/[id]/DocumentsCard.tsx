"use client";

import { Card, CardHeader, EmptyState, Skeleton } from "@/components/common/Card";
import { useCan } from "@/components/providers/SessionProvider";
import { api } from "@/lib/api";
import { type DocumentSummary, STATUS_TONE } from "@/lib/documents";
import { cn } from "@/utils";
import { useQuery } from "@tanstack/react-query";
import Link from "next/link";
import { useState } from "react";
import DocumentFormPanel from "./DocumentFormPanel";

const day = (iso: string) => new Date(iso).toLocaleDateString("es-CO", { day: "numeric", month: "short", year: "numeric" }).replace(/\./g, "");

/** Consents and authorizations of the pet, signed on screen. */
export default function DocumentsCard({ petId }: { petId: string }) {
  const canCreate = useCan("clinical_records.create");
  const [creating, setCreating] = useState(false);
  const docs = useQuery({ queryKey: ["pets", petId, "documents"], queryFn: () => api<{ data: DocumentSummary[] }>(`pets/${petId}/documents`).then((r) => r.data) });

  return (
    <Card>
      <CardHeader
        title="Documentos"
        description="Consentimientos y autorizaciones firmados en pantalla."
        action={
          canCreate && (
            <button type="button" onClick={() => setCreating(true)} className="text-theme-sm font-medium whitespace-nowrap text-brand-600 hover:underline">
              + Nuevo
            </button>
          )
        }
      />
      {!docs.data ? (
        <Skeleton className="m-5 h-12" />
      ) : docs.data.length === 0 ? (
        <EmptyState title="Sin documentos." description={canCreate ? "Eutanasia, cirugía, hospitalización, alta voluntaria…" : undefined} />
      ) : (
        <ul className="divide-y divide-gray-100 dark:divide-gray-800">
          {docs.data.map((d) => (
            <li key={d.id}>
              <Link href={`/app/documentos/${d.id}`} className="block px-5 py-3 hover:bg-gray-50 dark:hover:bg-white/2">
                <div className="flex items-center justify-between gap-3">
                  <p className={cn("min-w-0 truncate text-theme-sm font-medium text-gray-800 dark:text-white/90", d.status.value === "voided" && "text-gray-500 line-through")}>{d.short}</p>
                  <span className={cn("shrink-0 rounded-full px-2 py-0.5 text-[11px] font-medium", STATUS_TONE[d.status.value])}>{d.status.label}</span>
                </div>
                <p className="mt-0.5 truncate text-theme-xs text-gray-500">
                  {d.signed_at ? `Firmado ${day(d.signed_at)}` : `Creado ${day(d.created_at)}`}
                  {d.status.value !== "signed" && d.status.value !== "voided" && ` · ${d.signatures} de ${d.signers} firmas`}
                </p>
              </Link>
            </li>
          ))}
        </ul>
      )}
      {creating && <DocumentFormPanel petId={petId} onClose={() => setCreating(false)} />}
    </Card>
  );
}
