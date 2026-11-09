"use client";

import DocumentPaper from "@/components/domain/DocumentPaper";
import SignHere from "@/components/domain/SignHere";
import { Field, Input } from "@/components/form/fields";
import { useState } from "react";
import type { RemoteDocument } from "./page";

/** Read → confirm the last 4 ID digits → sign. Then a clear "done" screen. */
export default function RemoteSign({ token, doc }: { token: string; doc: RemoteDocument }) {
  const [last4, setLast4] = useState("");
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [done, setDone] = useState(false);

  if (done) {
    return (
      <section className="rounded-2xl border border-success-200 bg-white p-6 text-center dark:border-success-500/30 dark:bg-gray-900">
        <p className="text-3xl" aria-hidden>
          ✓
        </p>
        <h1 className="mt-2 text-lg font-semibold">¡Listo, {doc.owner_first_name}! Firmaste el documento</h1>
        <p className="mt-2 text-theme-sm text-gray-500">
          {doc.clinic.name} recibió tu firma. Cuando el documento tenga todas las firmas, la clínica te puede compartir el PDF. Ya puedes cerrar esta página.
        </p>
      </section>
    );
  }

  return (
    <>
      <section className="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900">
        <p className="text-theme-sm text-gray-500">Hola, {doc.owner_first_name}</p>
        <h1 className="text-lg font-semibold">{doc.clinic.name} te envió un documento para firmar</h1>
        <p className="mt-1 text-theme-sm text-gray-500">Léelo con calma. Al final firmas con el dedo.</p>
      </section>

      <DocumentPaper
        clinic={doc.clinic}
        title={doc.title}
        date={doc.date}
        facts={[
          ["Paciente", [doc.pet.name, doc.pet.species, doc.pet.breed].filter(Boolean).join(" · ")],
          ["Sexo · Edad · Peso", [doc.pet.sex, doc.pet.age, doc.pet.weight].filter(Boolean).join(" · ")],
          ["Tutor", doc.owner_name],
          ["Médico veterinario", `${doc.vet.name} · T.P. ${doc.vet.license}`],
        ]}
        blocks={doc.blocks}
      />

      <section className="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900">
        <SignHere
          busy={busy}
          error={error}
          before={
            <Field label="Para confirmar que eres tú: últimos 4 dígitos de tu documento" htmlFor="last4" hint={`Te quedan ${doc.attempts_left} intentos.`}>
              <Input
                id="last4"
                inputMode="numeric"
                autoComplete="off"
                maxLength={4}
                value={last4}
                onChange={(e) => setLast4(e.target.value.replace(/\D/g, ""))}
                className="w-32 text-center text-lg tracking-[0.5em] tabular"
              />
            </Field>
          }
          onSign={async (image) => {
            if (last4.length !== 4) {
              setError("Escribe los últimos 4 dígitos de tu documento.");
              return;
            }
            setBusy(true);
            setError(null);
            const response = await fetch(`/api/public/sign/${token}`, {
              method: "POST",
              headers: { "Content-Type": "application/json" },
              body: JSON.stringify({ document_last4: last4, image, accepted: true }),
            });
            const data = await response.json().catch(() => ({}));
            setBusy(false);
            if (response.ok) {
              setDone(true);
              return;
            }
            setError(
              response.status === 429
                ? "Demasiados intentos. Espera un minuto."
                : (data.errors?.document_last4?.[0] ?? data.errors?.image?.[0] ?? data.errors?.accepted?.[0] ?? data.message ?? "No fue posible firmar. Intenta de nuevo."),
            );
          }}
        />
      </section>
      <p className="text-center text-theme-xs text-gray-400">
        Firma electrónica (Ley 527 de 1999). Se guardan la fecha, la hora y el dispositivo como evidencia.
      </p>
    </>
  );
}
