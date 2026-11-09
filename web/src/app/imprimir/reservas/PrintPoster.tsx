"use client";

/* eslint-disable @next/next/no-img-element -- images from our own API (logo, QR) */
import { api } from "@/lib/api";
import type { OnlineBookingSettings } from "@/lib/booking";
import type { Me } from "@/lib/types";
import { useQuery } from "@tanstack/react-query";
import { useSearchParams } from "next/navigation";
import { useEffect, useRef } from "react";

/**
 * Counter poster (letter size): "Pide tu cita desde tu celular" with the QR
 * of the clinic's booking page (docs/reservas.md, B3). ?vista=1 shows it
 * without opening the print dialog.
 */
export default function PrintPoster() {
  const params = useSearchParams();
  const sheet = useRef<HTMLElement>(null);
  const printed = useRef(false);

  const me = useQuery({ queryKey: ["me-print"], queryFn: () => api<{ data: Me }>("me").then((r) => r.data) });
  const settings = useQuery({ queryKey: ["online-booking", "settings"], queryFn: () => api<{ data: OnlineBookingSettings }>("online-booking/settings").then((r) => r.data) });

  // Print once the logo and the QR have loaded.
  useEffect(() => {
    if (!me.data || !settings.data || printed.current || !sheet.current || params.get("vista") === "1") return;
    const images = Array.from(sheet.current.querySelectorAll("img"));
    Promise.all(
      images.map((img) =>
        img.complete
          ? Promise.resolve()
          : new Promise<void>((resolve) => {
              img.addEventListener("load", () => resolve(), { once: true });
              img.addEventListener("error", () => resolve(), { once: true });
            }),
      ),
    ).then(() => {
      printed.current = true;
      setTimeout(() => window.print(), 300);
    });
  }, [me.data, settings.data, params]);

  if (!me.data || !settings.data) return <p style={{ padding: 40, fontFamily: "sans-serif" }}>Preparando el cartel…</p>;

  const clinic = me.data.clinic;
  const url = settings.data.public_url.replace(/^https?:\/\//, "");

  return (
    <main ref={sheet} className="min-h-screen bg-white text-gray-900" style={{ fontFamily: "var(--font-jakarta), sans-serif" }}>
      <style>{"@page { size: letter; margin: 0; } @media print { .no-print { display: none !important; } }"}</style>
      <p className="no-print bg-gray-100 p-3 text-center text-sm text-gray-600">
        Vista previa del cartel. <button type="button" onClick={() => window.print()} className="font-semibold text-brand-600 underline">Imprimir</button>
      </p>
      <div className="mx-auto flex min-h-[279mm] w-[216mm] flex-col items-center px-[18mm] py-[16mm] text-center">
        <div className="flex items-center gap-4">
          {clinic.logo_url && <img src={`/api/backend/${clinic.logo_url}`} alt="" className="h-20 w-auto max-w-[60mm] object-contain" />}
          <p className="text-left text-3xl font-bold">{clinic.name}</p>
        </div>

        <div className="mt-[14mm] rounded-full bg-brand-50 px-6 py-2 text-lg font-semibold text-brand-700">Reserva en línea</div>
        <h1 className="mt-6 text-[44pt] leading-tight font-bold tracking-tight">
          Pide tu cita
          <br />
          desde tu celular
        </h1>

        <div className="mt-[10mm] rounded-3xl border-4 border-brand-500 p-4">
          <img src="/api/backend/online-booking/qr" alt="Código QR para pedir una cita" className="size-[78mm]" style={{ imageRendering: "pixelated" }} />
        </div>
        <p className="mt-4 text-xl font-semibold text-brand-700">{url}</p>

        <ol className="mt-[12mm] grid w-full grid-cols-3 gap-6 text-left">
          {[
            ["Escanea", "el código con la cámara de tu celular."],
            ["Elige", "el servicio, el día y la hora."],
            ["Te confirmamos", "por WhatsApp."],
          ].map(([title, text], i) => (
            <li key={title} className="flex gap-3">
              <span className="flex size-10 shrink-0 items-center justify-center rounded-full bg-brand-500 text-lg font-bold text-white">{i + 1}</span>
              <span className="text-base leading-snug">
                <b>{title}</b> {text}
              </span>
            </li>
          ))}
        </ol>

        <p className="mt-auto pt-[10mm] text-sm text-gray-400">Reservas con Zooetia</p>
      </div>
    </main>
  );
}
