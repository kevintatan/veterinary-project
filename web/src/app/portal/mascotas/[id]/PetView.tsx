"use client";

import { downloadCard } from "@/components/pet-card/download";
import Link from "next/link";
import { type CardSide, PetCard } from "@/components/pet-card/PetCard";
import { appointmentWhen, type PortalPet, TUTOR_BASE } from "@/lib/portal";
import { cn } from "@/utils";
import { useEffect, useRef, useState } from "react";

const CARD_WIDTH_EM = 85.6;
const CARD_HEIGHT_EM = 54;

const formatDay = (day: string) =>
  new Intl.DateTimeFormat("es-CO", { day: "numeric", month: "short", year: "numeric", timeZone: "UTC" }).format(new Date(`${day}T00:00:00Z`)).replace(/\./g, "");

/**
 * The pet in the owner portal: the same card the clinic designed (tap to flip),
 * download as PNG, vaccines and upcoming appointments.
 */
export default function PetView({ pet, bookFor = null }: { pet: PortalPet; bookFor?: { id: string; name: string } | null }) {
  const box = useRef<HTMLDivElement>(null);
  const front = useRef<HTMLDivElement>(null);
  const back = useRef<HTMLDivElement>(null);
  const [unit, setUnit] = useState(4);
  const [side, setSide] = useState<CardSide>("front");
  const [exporting, setExporting] = useState(false);

  // The card is drawn in em: fit it to the phone's width.
  useEffect(() => {
    const el = box.current;
    if (!el) return;
    const fit = () => setUnit(Math.min(6, el.clientWidth / CARD_WIDTH_EM));
    fit();
    const observer = new ResizeObserver(fit);
    observer.observe(el);
    return () => observer.disconnect();
  }, []);

  const download = async () => {
    if (!front.current || !back.current) return;
    setExporting(true);
    try {
      await downloadCard(front.current, back.current, pet.pet.name);
    } finally {
      setExporting(false);
    }
  };

  return (
    <main className="mx-auto max-w-md space-y-5 px-4 py-6">
      <div ref={box}>
        {/*
          3D flip, like turning a real card over: both faces live in the same
          spot, the back one pre-rotated 180°, and the whole card turns. The
          rotation is on the wrappers, so the PNG export (refs on the cards)
          captures each face straight.
        */}
        <button
          type="button"
          onClick={() => setSide((s) => (s === "front" ? "back" : "front"))}
          className="block w-full text-left [perspective:1600px]" /* buttons center text by default: keep the card as designed */
          style={{ height: `${CARD_HEIGHT_EM * unit}px` }}
          aria-label={side === "front" ? "Ver el reverso del carné" : "Ver el frente del carné"}
        >
          <div
            className="relative size-full transition-transform duration-700 ease-[cubic-bezier(.3,.7,.3,1)] [transform-style:preserve-3d] motion-reduce:transition-none"
            style={{ transform: side === "back" ? "rotateY(180deg)" : "none" }}
          >
            <div className="absolute inset-0 [backface-visibility:hidden]" aria-hidden={side !== "front"}>
              <PetCard ref={front} data={pet} design={pet.design} side="front" unit={`${unit}px`} base={TUTOR_BASE} />
            </div>
            <div className="absolute inset-0 [transform:rotateY(180deg)] [backface-visibility:hidden]" aria-hidden={side !== "back"}>
              <PetCard ref={back} data={pet} design={pet.design} side="back" unit={`${unit}px`} base={TUTOR_BASE} />
            </div>
          </div>
        </button>
        <p className="mt-2 text-center text-theme-xs text-gray-500">Toca el carné para verlo por {side === "front" ? "detrás (QR)" : "delante"}.</p>
      </div>

      <div className="grid grid-cols-2 gap-2">
        <button
          type="button"
          onClick={download}
          disabled={exporting}
          className="rounded-xl bg-brand-500 px-4 py-3 text-sm font-semibold text-white shadow-theme-xs hover:bg-brand-600 disabled:opacity-60"
        >
          {exporting ? "Preparando…" : "Descargar carné"}
        </button>
        <a
          href={pet.public_url}
          target="_blank"
          rel="noreferrer"
          className="rounded-xl border border-gray-300 bg-white px-4 py-3 text-center text-sm font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200"
        >
          Ver página del QR
        </a>
      </div>

      <Section title="Próximas citas">
        {bookFor && (
          <Link href={`/portal/agendar?mascota=${bookFor.id}`} className="mx-4 mt-4 flex items-center justify-center rounded-xl bg-brand-500 py-2.5 text-theme-sm font-semibold text-white hover:bg-brand-600">
            Pedir una cita para {bookFor.name}
          </Link>
        )}
        {pet.upcoming.length === 0 ? (
          <p className="px-4 py-4 text-theme-sm text-gray-500">No tiene citas agendadas.</p>
        ) : (
          <ul className="divide-y divide-gray-100 dark:divide-gray-800">
            {pet.upcoming.map((a) => (
              <li key={a.id} className="px-4 py-3">
                <p className="text-theme-sm font-medium first-letter:uppercase">{appointmentWhen(a)}</p>
                <p className="text-theme-xs text-gray-500">
                  {[a.service ?? "Cita", a.branch?.name, a.status.value === "confirmed" ? "Confirmada" : null].filter(Boolean).join(" · ")}
                </p>
                {a.branch?.address && <p className="text-theme-xs text-gray-400">{a.branch.address}</p>}
              </li>
            ))}
          </ul>
        )}
      </Section>

      <Section title="Vacunas">
        {pet.vaccinations.length === 0 ? (
          <p className="px-4 py-4 text-theme-sm text-gray-500">No hay vacunas registradas.</p>
        ) : (
          <ul className="divide-y divide-gray-100 dark:divide-gray-800">
            {pet.vaccinations.map((v) => (
              <li key={`${v.vaccine}-${v.applied_on}`} className="flex items-center justify-between gap-3 px-4 py-3">
                <div className="min-w-0">
                  <p className="truncate text-theme-sm font-medium">{v.vaccine}</p>
                  <p className="text-theme-xs text-gray-500">
                    Aplicada {formatDay(v.applied_on)}
                    {v.valid_until && ` · refuerzo ${formatDay(v.valid_until)}`}
                  </p>
                </div>
                <span
                  className={cn(
                    "shrink-0 rounded-full px-2 py-0.5 text-[11px] font-medium",
                    v.status === "valid" && "bg-success-50 text-success-700 dark:bg-success-500/10 dark:text-success-400",
                    v.status === "expired" && "bg-warning-50 text-warning-700 dark:bg-warning-500/10 dark:text-warning-400",
                    v.status === "no_booster" && "bg-gray-100 text-gray-600 dark:bg-white/5 dark:text-gray-300",
                  )}
                >
                  {v.label}
                </span>
              </li>
            ))}
          </ul>
        )}
      </Section>

      <Section title={pet.clinic.name}>
        <div className="flex items-center justify-between gap-3 px-4 py-3">
          <p className="text-theme-sm text-gray-500">¿Necesitas una cita o tienes una pregunta?</p>
          {pet.clinic.phone && (
            <a href={`tel:${pet.clinic.phone.replace(/\s/g, "")}`} className="shrink-0 text-theme-sm font-semibold text-brand-600">
              Llamar
            </a>
          )}
        </div>
      </Section>
    </main>
  );
}

function Section({ title, children }: { title: string; children: React.ReactNode }) {
  return (
    <section className="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
      <h2 className="border-b border-gray-100 px-4 py-3 text-theme-sm font-semibold dark:border-gray-800">{title}</h2>
      {children}
    </section>
  );
}
