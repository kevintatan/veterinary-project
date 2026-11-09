"use client";

import { Card, Skeleton } from "@/components/common/Card";
import PetPhotoUploader from "@/components/domain/PetPhotoUploader";
import { PetCard } from "@/components/pet-card/PetCard";
import { CARD_COLORS, type CardData, type CardDesign, TEMPLATES } from "@/components/pet-card/types";
import { useCan } from "@/components/providers/SessionProvider";
import Button from "@/components/ui/button/Button";
import { api } from "@/lib/api";
import { cn } from "@/utils";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { downloadCard } from "@/components/pet-card/download";
import Link from "next/link";
import { useRef, useState } from "react";

/** Query string for the print page (the design may not be saved yet). */
export function designQuery(d: CardDesign): string {
  return new URLSearchParams({
    t: d.template,
    c: d.color.replace("#", ""),
    p: d.show_photo ? "1" : "0",
    o: d.show_owner_phone ? "1" : "0",
    v: d.show_vaccines ? "1" : "0",
  }).toString();
}

export default function CardDesigner({ petId }: { petId: string }) {
  const canEdit = useCan("patients.manage");
  const isAdmin = useCan("clinic.settings.manage");
  const queryClient = useQueryClient();

  const card = useQuery({
    queryKey: ["pets", petId, "card-design"],
    queryFn: () => api<{ data: CardData }>(`pets/${petId}/card`).then((r) => r.data),
  });

  const [draft, setDraft] = useState<CardDesign | null>(null);
  const design = draft ?? card.data?.design ?? null;
  const dirty = !!draft && !!card.data && JSON.stringify(draft) !== JSON.stringify(card.data.design);
  const set = (patch: Partial<CardDesign>) => design && setDraft({ ...design, ...patch });

  const front = useRef<HTMLDivElement>(null);
  const back = useRef<HTMLDivElement>(null);
  const [exporting, setExporting] = useState(false);

  const saveForPet = useMutation({
    mutationFn: (value: CardDesign | null) => api(`pets/${petId}/card-design`, { method: "PUT", body: { design: value } }),
    onSuccess: () => {
      setDraft(null);
      queryClient.invalidateQueries({ queryKey: ["pets", petId, "card-design"] });
    },
  });
  const saveForClinic = useMutation({
    mutationFn: (value: CardDesign) => api("clinic/pet-card", { method: "PUT", body: value }),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ["pets", petId, "card-design"] }),
  });

  const download = async () => {
    if (!front.current || !back.current || !card.data) return;
    setExporting(true);
    try {
      await downloadCard(front.current, back.current, card.data.pet.name);
    } finally {
      setExporting(false);
    }
  };

  if (card.isLoading || !design || !card.data) {
    return <Skeleton className="h-[520px]" />;
  }

  const data = card.data;

  return (
    <>
      <div className="mb-6 flex flex-wrap items-end justify-between gap-4">
        <div>
          <Link href={`/app/mascotas/${petId}`} className="text-theme-sm text-gray-500 hover:text-gray-800 dark:hover:text-gray-200">
            ← Volver a {data.pet.name}
          </Link>
          <h1 className="mt-1 text-theme-xl font-semibold text-gray-800 dark:text-white/90">Carné de {data.pet.name}</h1>
          <p className="text-theme-sm text-gray-500 dark:text-gray-400">Tamaño real de tarjeta de identificación: 8,6 × 5,4 cm.</p>
        </div>
        <div className="flex flex-wrap gap-2">
          <Button variant="outline" size="sm" loading={exporting} onClick={download}>
            Descargar imagen
          </Button>
          <a
            href={`/imprimir/carne/${petId}?${designQuery(design)}`}
            target="_blank"
            rel="noopener"
            className="inline-flex items-center rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white shadow-theme-xs hover:bg-brand-600"
          >
            Imprimir
          </a>
        </div>
      </div>

      <div className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_380px]">
        {/* Preview */}
        <div
          className="flex flex-col items-center gap-8 overflow-x-auto rounded-2xl border border-gray-200 px-6 py-10 dark:border-gray-800"
          style={{ backgroundColor: "#eef1f5", backgroundImage: "radial-gradient(#d0d5dd 0.6px, transparent 0.6px)", backgroundSize: "14px 14px" }}
        >
          <figure className="flex flex-col items-center gap-2">
            <PetCard ref={front} data={data} design={design} side="front" unit="6px" />
            <figcaption className="text-theme-xs font-medium text-gray-500">Frente</figcaption>
          </figure>
          <figure className="flex flex-col items-center gap-2">
            <PetCard ref={back} data={data} design={design} side="back" unit="6px" />
            <figcaption className="text-theme-xs font-medium text-gray-500">Reverso</figcaption>
          </figure>
        </div>

        {/* Controls */}
        <Card className="h-fit">
          <div className="space-y-6 p-5">
            <section>
              <h2 className="mb-3 text-theme-xs font-semibold tracking-wide text-gray-400 uppercase">Plantilla</h2>
              <div className="space-y-2.5">
                {TEMPLATES.map((t) => (
                  <button
                    key={t.value}
                    type="button"
                    onClick={() => set({ template: t.value })}
                    aria-pressed={design.template === t.value}
                    className={cn(
                      "flex w-full items-center gap-3 rounded-xl border p-2.5 text-left transition",
                      design.template === t.value ? "border-brand-500 bg-brand-50/60 ring-1 ring-brand-500 dark:bg-brand-500/10" : "border-gray-200 hover:border-gray-300 dark:border-gray-800",
                    )}
                  >
                    <div className="pointer-events-none shrink-0">
                      <PetCard data={data} design={{ ...design, template: t.value }} side="front" unit="1.5px" />
                    </div>
                    <span className="min-w-0">
                      <span className="block text-theme-sm font-semibold text-gray-800 dark:text-white/90">{t.label}</span>
                      <span className="block text-theme-xs text-gray-500">{t.description}</span>
                    </span>
                  </button>
                ))}
              </div>
            </section>

            <section>
              <h2 className="mb-3 text-theme-xs font-semibold tracking-wide text-gray-400 uppercase">Color</h2>
              <div className="flex flex-wrap gap-2" role="radiogroup" aria-label="Color del carné">
                {CARD_COLORS.map((c) => (
                  <button
                    key={c.value}
                    type="button"
                    role="radio"
                    aria-checked={design.color.toLowerCase() === c.value.toLowerCase()}
                    title={c.label}
                    onClick={() => set({ color: c.value })}
                    className={cn("h-8 w-8 rounded-full ring-offset-2 transition dark:ring-offset-gray-900", design.color.toLowerCase() === c.value.toLowerCase() && "ring-2 ring-gray-900 dark:ring-white")}
                    style={{ backgroundColor: c.value }}
                  />
                ))}
                <label title="Otro color" className="relative flex h-8 w-8 cursor-pointer items-center justify-center rounded-full border-2 border-dashed border-gray-300 text-gray-500 dark:border-gray-600">
                  +
                  <input type="color" value={design.color} onChange={(e) => set({ color: e.target.value })} className="absolute inset-0 cursor-pointer opacity-0" aria-label="Elegir otro color" />
                </label>
              </div>
            </section>

            <section className="space-y-3">
              <h2 className="text-theme-xs font-semibold tracking-wide text-gray-400 uppercase">Mostrar</h2>
              <Toggle checked={design.show_photo} onChange={(v) => set({ show_photo: v })} label="Foto de la mascota" hint={data.pet.photo_url ? undefined : "Aún no tiene foto: mientras tanto se ve una huella."} />
              {canEdit && design.show_photo && (
                <div className="rounded-xl bg-gray-50 p-3 dark:bg-white/3">
                  <PetPhotoUploader petId={petId} name={data.pet.name} photoUrl={data.pet.photo_url} speciesCode={data.pet.species_code} size="md" layout="inline" />
                </div>
              )}
              <Toggle checked={design.show_vaccines} onChange={(v) => set({ show_vaccines: v })} label="Vacunas en el reverso" hint="El QR siempre muestra el estado actualizado." />
              <Toggle checked={design.show_owner_phone} onChange={(v) => set({ show_owner_phone: v })} label="Celular del tutor" hint="Útil si se pierde. Pregúntale al tutor si quiere que su número vaya impreso." />
            </section>

            {canEdit && (
              <section className="space-y-2 border-t border-gray-100 pt-5 dark:border-gray-800">
                <Button className="w-full" size="sm" disabled={!dirty} loading={saveForPet.isPending} onClick={() => saveForPet.mutate(design)}>
                  {dirty ? "Guardar diseño de este carné" : "Diseño guardado"}
                </Button>
                {data.has_own_design && !dirty && (
                  <button type="button" onClick={() => saveForPet.mutate(null)} className="w-full text-center text-theme-xs text-gray-500 hover:text-gray-800 dark:hover:text-gray-200">
                    Volver al diseño de la clínica
                  </button>
                )}
                {isAdmin && (
                  <button
                    type="button"
                    disabled={saveForClinic.isPending || JSON.stringify(design) === JSON.stringify(data.clinic_design)}
                    onClick={() => saveForClinic.mutate(design)}
                    className="w-full text-center text-theme-xs font-medium text-brand-600 hover:underline disabled:text-gray-400 disabled:no-underline dark:text-brand-400"
                  >
                    {JSON.stringify(design) === JSON.stringify(data.clinic_design) ? "Es el diseño de la clínica" : "Usar como diseño de la clínica para todos los carnés"}
                  </button>
                )}
              </section>
            )}
          </div>
        </Card>
      </div>
    </>
  );
}

function Toggle({ checked, onChange, label, hint }: { checked: boolean; onChange: (v: boolean) => void; label: string; hint?: string }) {
  return (
    <label className="flex cursor-pointer items-start justify-between gap-4">
      <span>
        <span className="block text-theme-sm text-gray-800 dark:text-white/90">{label}</span>
        {hint && <span className="block text-theme-xs text-gray-500">{hint}</span>}
      </span>
      <button
        type="button"
        role="switch"
        aria-checked={checked}
        aria-label={label}
        onClick={() => onChange(!checked)}
        className={cn("relative mt-0.5 h-6 w-11 shrink-0 rounded-full transition", checked ? "bg-brand-500" : "bg-gray-200 dark:bg-gray-700")}
      >
        <span className={cn("absolute top-0.5 left-0.5 h-5 w-5 rounded-full bg-white shadow-theme-xs transition", checked && "translate-x-5")} />
      </button>
    </label>
  );
}
