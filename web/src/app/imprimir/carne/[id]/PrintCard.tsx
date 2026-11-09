"use client";

import { PetCard } from "@/components/pet-card/PetCard";
import type { CardData, CardDesign, CardTemplate } from "@/components/pet-card/types";
import { api } from "@/lib/api";
import { useQuery } from "@tanstack/react-query";
import { useSearchParams } from "next/navigation";
import { useEffect, useRef } from "react";

/**
 * Print sheet: front and back at real size (1em = 1mm) with cut marks.
 * Folded along the middle line and laminated, it becomes a two-sided card.
 */
export default function PrintCard({ petId }: { petId: string }) {
  const params = useSearchParams();
  const sheet = useRef<HTMLDivElement>(null);
  const printed = useRef(false);

  const card = useQuery({
    queryKey: ["pets", petId, "card-design"],
    queryFn: () => api<{ data: CardData }>(`pets/${petId}/card`).then((r) => r.data),
  });

  const saved = card.data?.design;
  const design: CardDesign | undefined = saved && {
    template: (["classic", "modern", "paws"].includes(params.get("t") ?? "") ? params.get("t") : saved.template) as CardTemplate,
    color: /^[0-9a-fA-F]{6}$/.test(params.get("c") ?? "") ? `#${params.get("c")}` : saved.color,
    show_photo: params.has("p") ? params.get("p") === "1" : saved.show_photo,
    show_owner_phone: params.has("o") ? params.get("o") === "1" : saved.show_owner_phone,
    show_vaccines: params.has("v") ? params.get("v") === "1" : saved.show_vaccines,
  };

  // Print once every image (photo, logo, QR) has loaded.
  useEffect(() => {
    // ?vista=1: check the sheet without opening the print dialog.
    if (!card.data || printed.current || !sheet.current || params.get("vista") === "1") return;
    const images = Array.from(sheet.current.querySelectorAll("img"));
    const loaded = (img: HTMLImageElement) =>
      img.complete
        ? Promise.resolve()
        : new Promise<void>((resolve) => {
            img.addEventListener("load", () => resolve(), { once: true });
            img.addEventListener("error", () => resolve(), { once: true });
          });
    Promise.all(images.map(loaded)).then(() => {
      printed.current = true;
      setTimeout(() => window.print(), 300);
    });
  }, [card.data, params]);

  if (!card.data || !design) {
    return <p style={{ padding: 40, fontFamily: "sans-serif" }}>Preparando el carné…</p>;
  }

  const crop: React.CSSProperties = { position: "absolute", background: "#98A2B3" };

  return (
    <main style={{ padding: "12mm", fontFamily: "var(--font-jakarta), sans-serif", color: "#111827", background: "#fff", minHeight: "100vh" }}>
      <style>{`@page { size: letter; margin: 10mm; } @media print { .no-print { display: none !important; } main { padding: 0 !important; } }`}</style>

      <div className="no-print" style={{ marginBottom: "8mm", fontSize: 14, color: "#475467", maxWidth: 640 }}>
        <b style={{ color: "#111827" }}>Carné de {card.data.pet.name}.</b> En el cuadro de impresión elige <b>tamaño real (100 %)</b> y activa <b>gráficos de fondo</b> para que salgan los colores. Recorta por las marcas, dobla por la línea del centro y plastifica.
        <button type="button" onClick={() => window.print()} style={{ marginLeft: 12, padding: "6px 14px", borderRadius: 8, background: "#166534", color: "#fff", border: 0, cursor: "pointer" }}>
          Imprimir
        </button>
      </div>

      <div ref={sheet} style={{ position: "relative", display: "inline-flex", flexDirection: "column", padding: "4mm" }}>
        {/* Cut marks at the four corners */}
        {[
          { top: 0, left: "4mm", width: "0.2mm", height: "3mm" }, { top: "4mm", left: 0, width: "3mm", height: "0.2mm" },
          { top: 0, right: "4mm", width: "0.2mm", height: "3mm" }, { top: "4mm", right: 0, width: "3mm", height: "0.2mm" },
          { bottom: 0, left: "4mm", width: "0.2mm", height: "3mm" }, { bottom: "4mm", left: 0, width: "3mm", height: "0.2mm" },
          { bottom: 0, right: "4mm", width: "0.2mm", height: "3mm" }, { bottom: "4mm", right: 0, width: "3mm", height: "0.2mm" },
        ].map((s, i) => (
          <span key={i} style={{ ...crop, ...s }} />
        ))}
        <PetCard data={card.data} design={design} side="front" unit="1mm" />
        {/* Fold line */}
        <div style={{ height: 0, borderTop: "0.2mm dashed #98A2B3", margin: "0 -3mm" }} />
        <PetCard data={card.data} design={design} side="back" unit="1mm" />
      </div>
    </main>
  );
}
