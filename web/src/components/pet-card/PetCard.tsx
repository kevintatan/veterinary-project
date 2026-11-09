/* eslint-disable @next/next/no-img-element -- private images behind the BFF; html-to-image needs plain <img> */
import { createContext, forwardRef, useContext } from "react";
import { alpha, ink, onColor, shade, tint } from "./color";
import type { CardInfo as CardData, CardDesign } from "./types";

/**
 * The pet ID card, drawn in real millimeters: 1em = 1mm, card = 85.6 × 54 em
 * (ISO/IEC 7810 ID-1, the size of an ID card). The same component is used for
 * the on-screen preview (font-size in px), the PNG export and printing
 * (font-size: 1mm), so what you see is exactly what gets printed.
 */

export type CardSide = "front" | "back";

/** base: the BFF the card images go through (staff "/api/backend", owner portal "/api/tutor"). */
type Props = { data: CardData; design: CardDesign; side: CardSide; unit?: string; base?: string };

const W = 85.6;
const H = 54;

export const PetCard = forwardRef<HTMLDivElement, Props>(function PetCard({ data, design, side, unit = "5px", base = "/api/backend" }, ref) {
  const Template = { classic: Classic, modern: Modern, paws: Paws }[design.template];

  return (
    <div
      ref={ref}
      style={{
        fontSize: unit,
        width: `${W}em`,
        height: `${H}em`,
        borderRadius: "3.2em",
        overflow: "hidden",
        position: "relative",
        fontFamily: "var(--font-jakarta), ui-sans-serif, system-ui, sans-serif",
        lineHeight: 1.25,
        color: "#111827",
        background: "#ffffff",
        boxShadow: "0 0.3em 1.2em rgba(16,24,40,.18), 0 0 0 0.1em rgba(16,24,40,.06)",
        WebkitPrintColorAdjust: "exact",
        printColorAdjust: "exact",
      }}
    >
      <ImageBase.Provider value={base}>
        <Template data={data} design={design} side={side} />
      </ImageBase.Provider>
    </div>
  );
});

// ---------------------------------------------------------------- shared pieces

const ImageBase = createContext("/api/backend");

const PAW = "M16 14c-4.2 0-8 5-8 8.4 0 2.3 1.7 3.4 3.7 3.4 1.6 0 2.8-.8 4.3-.8s2.7.8 4.3.8c2 0 3.7-1.1 3.7-3.4C24 19 20.2 14 16 14Z";

function Paw({ color, size, style }: { color: string; size: number; style?: React.CSSProperties }) {
  return (
    <svg viewBox="0 0 32 32" style={{ width: `${size}em`, height: `${size}em`, ...style }} aria-hidden>
      <g fill={color}>
        <ellipse cx="8" cy="12.2" rx="2.6" ry="3.2" />
        <ellipse cx="13" cy="7.8" rx="2.6" ry="3.2" />
        <ellipse cx="19" cy="7.8" rx="2.6" ry="3.2" />
        <ellipse cx="24" cy="12.2" rx="2.6" ry="3.2" />
        <path d={PAW} />
      </g>
    </svg>
  );
}

/** Tiled paw prints as a CSS background (data URI SVG). */
function pawPattern(color: string, opacity: number): string {
  const c = encodeURIComponent(color);
  const svg = `<svg xmlns='http://www.w3.org/2000/svg' width='64' height='64' viewBox='0 0 64 64'><g fill='${c}' fill-opacity='${opacity}'><g transform='translate(4 6) rotate(-18 16 16)'><ellipse cx='8' cy='12.2' rx='2.6' ry='3.2'/><ellipse cx='13' cy='7.8' rx='2.6' ry='3.2'/><ellipse cx='19' cy='7.8' rx='2.6' ry='3.2'/><ellipse cx='24' cy='12.2' rx='2.6' ry='3.2'/><path d='${PAW}'/></g><g transform='translate(34 34) scale(.75) rotate(22 16 16)'><ellipse cx='8' cy='12.2' rx='2.6' ry='3.2'/><ellipse cx='13' cy='7.8' rx='2.6' ry='3.2'/><ellipse cx='19' cy='7.8' rx='2.6' ry='3.2'/><ellipse cx='24' cy='12.2' rx='2.6' ry='3.2'/><path d='${PAW}'/></g></g></svg>`;
  return `url("data:image/svg+xml,${svg.replace(/"/g, "'").replace(/#/g, "%23")}")`;
}

const ellipsis: React.CSSProperties = { whiteSpace: "nowrap", overflow: "hidden", textOverflow: "ellipsis" };

function birth(data: CardData): string | null {
  const b = data.pet.birth_date;
  if (!b) return null;
  const d = new Date(`${b}T00:00:00Z`);
  const text = data.pet.birth_date_is_estimated
    ? new Intl.DateTimeFormat("es-CO", { month: "short", year: "numeric", timeZone: "UTC" }).format(d).replace(/\./g, "").replace(" de ", " ")
    : new Intl.DateTimeFormat("es-CO", { day: "2-digit", month: "2-digit", year: "numeric", timeZone: "UTC" }).format(d);
  return data.pet.birth_date_is_estimated ? `≈ ${text}` : text;
}

const dmy = (day: string) => `${day.slice(8, 10)}/${day.slice(5, 7)}/${day.slice(0, 4)}`;

function phone(p: string | null): string | null {
  if (!p) return null;
  const m = p.match(/^\+57(\d{3})(\d{3})(\d{4})$/);
  return m ? `${m[1]} ${m[2]} ${m[3]}` : p;
}

function Photo({ data, design, size, round, ring, ringStyle = "solid", radius = 2 }: { data: CardData; design: CardDesign; size: [number, number]; round?: boolean; ring: string; ringStyle?: "solid" | "dashed"; radius?: number }) {
  const shape: React.CSSProperties = {
    width: `${size[0]}em`,
    height: `${size[1]}em`,
    borderRadius: round ? "50%" : `${radius}em`,
    border: `0.7em ${ringStyle} ${ring}`,
    boxShadow: "0 0.4em 1.2em rgba(16,24,40,.18)",
    flexShrink: 0,
  };

  const base = useContext(ImageBase);

  if (design.show_photo && data.pet.photo_url) {
    return <img src={`${base}/${data.pet.photo_url}`} alt="" style={{ ...shape, objectFit: "cover", background: "#fff" }} crossOrigin="anonymous" />;
  }

  return (
    <div style={{ ...shape, background: tint(design.color, 0.85), display: "flex", alignItems: "center", justifyContent: "center" }}>
      <Paw color={design.color} size={Math.min(size[0], size[1]) * 0.5} />
    </div>
  );
}

function ClinicMark({ data, design, size, on }: { data: CardData; design: CardDesign; size: number; on: string }) {
  const base = useContext(ImageBase);

  return (
    <span style={{ width: `${size}em`, height: `${size}em`, borderRadius: "50%", background: "#fff", display: "inline-flex", alignItems: "center", justifyContent: "center", flexShrink: 0, overflow: "hidden", boxShadow: on === "#ffffff" ? "none" : `0 0 0 0.2em ${alpha("#000000", 0.08)}` }}>
      {data.clinic.logo_url ? (
        <img src={`${base}/${data.clinic.logo_url}`} alt="" style={{ width: "82%", height: "82%", objectFit: "contain" }} crossOrigin="anonymous" />
      ) : (
        <Paw color={design.color} size={size * 0.62} />
      )}
    </span>
  );
}

function Field({ label, value, labelColor, valueColor, width }: { label: string; value: string | null; labelColor: string; valueColor: string; width?: string }) {
  return (
    <div style={{ width, minWidth: 0 }}>
      <div style={{ fontSize: "1.55em", letterSpacing: "0.04em", textTransform: "uppercase", color: labelColor, fontWeight: 600 }}>{label}</div>
      <div style={{ fontSize: "2.35em", fontWeight: 700, color: valueColor, marginTop: "0.08em", ...ellipsis }}>{value || "—"}</div>
    </div>
  );
}

function Qr({ data, size, border }: { data: CardData; size: number; border: string }) {
  const base = useContext(ImageBase);

  return (
    <div style={{ width: `${size}em`, height: `${size}em`, background: "#fff", borderRadius: "2em", padding: "1.2em", border: `0.35em solid ${border}`, boxSizing: "border-box", flexShrink: 0 }}>
      <img src={`${base}/${data.qr_url}`} alt="QR" style={{ width: "100%", height: "100%", imageRendering: "pixelated", display: "block" }} crossOrigin="anonymous" />
    </div>
  );
}

function VaccineList({ data, design, accent, max = 3 }: { data: CardData; design: CardDesign; accent: string; max?: number }) {
  if (!design.show_vaccines) return null;
  // A card has room for a few: the API sends them by importance (rabies first, then the most
  // recent). If there are more, the last row says how many are left: the QR page shows them all.
  const overflow = data.vaccinations.length > max;
  const list = data.vaccinations.slice(0, overflow ? max - 1 : max);
  const hidden = data.vaccinations.length - list.length;

  if (list.length === 0) {
    return <div style={{ fontSize: "1.9em", color: "#667085" }}>Sin vacunas registradas aún.</div>;
  }

  return (
    <div style={{ display: "flex", flexDirection: "column", gap: "0.9em" }}>
      {list.map((v) => {
        const ok = v.status === "valid";
        return (
          <div key={v.vaccine} style={{ display: "flex", alignItems: "center", gap: "1.2em", minWidth: 0 }}>
            {/* Size set in the parent's em (1em = 1mm); the glyph has its own font size inside. */}
            <span
              style={{
                width: "3.2em", height: "3.2em", borderRadius: "50%", flexShrink: 0, display: "inline-flex", alignItems: "center", justifyContent: "center",
                background: ok ? accent : v.status === "expired" ? "#D92D20" : "#98A2B3",
              }}
            >
              <span style={{ color: onColor(ok ? accent : "#D92D20"), fontSize: "2em", fontWeight: 800, lineHeight: 1 }}>{ok ? "✓" : v.status === "expired" ? "!" : "·"}</span>
            </span>
            <div style={{ minWidth: 0 }}>
              <div style={{ fontSize: "2.05em", fontWeight: 700, ...ellipsis }}>{v.vaccine}</div>
              <div style={{ fontSize: "1.6em", color: "#475467" }}>{v.valid_until ? `${ok ? "Vigente" : "Venció"} hasta ${dmy(v.valid_until)}` : v.label}</div>
            </div>
          </div>
        );
      })}
      {overflow && (
        <div style={{ display: "flex", alignItems: "center", gap: "1.2em" }}>
          <span style={{ width: "3.2em", height: "3.2em", borderRadius: "50%", flexShrink: 0, display: "inline-flex", alignItems: "center", justifyContent: "center", background: tint(accent, 0.85) }}>
            <span style={{ color: accent, fontSize: "1.6em", fontWeight: 800, lineHeight: 1 }}>+{hidden}</span>
          </span>
          <div style={{ fontSize: "1.7em", fontWeight: 600, color: "#475467", ...ellipsis }}>
            {hidden === 1 ? "1 vacuna más" : `${hidden} vacunas más`} · escanea el QR
          </div>
        </div>
      )}
    </div>
  );
}

function Emergency({ data, design, color }: { data: CardData; design: CardDesign; color: string }) {
  const owner = design.show_owner_phone ? phone(data.owner.mobile_phone) : null;
  const clinic = data.clinic.phone;
  if (!owner && !clinic) return null;

  return (
    <div style={{ fontSize: "1.7em", color, lineHeight: 1.35 }}>
      {owner && (
        <div style={ellipsis}>
          <b>Si me encuentras:</b> {data.owner.full_name?.split(" ")[0]} · {owner}
        </div>
      )}
      {clinic && (
        <div style={ellipsis}>
          {data.clinic.name} · {clinic}
        </div>
      )}
    </div>
  );
}

// ---------------------------------------------------------------- Clásico (ID / cédula)

function Classic({ data, design, side }: { data: CardData; design: CardDesign; side: CardSide }) {
  const c = design.color;
  const on = onColor(c);
  const dark = ink(c);
  const security = `repeating-linear-gradient(45deg, ${alpha(c, 0.045)} 0 0.35em, transparent 0.35em 1.6em), repeating-linear-gradient(-45deg, ${alpha(c, 0.035)} 0 0.35em, transparent 0.35em 1.6em)`;

  const band = (height: number, title: string) => (
    <>
      <div style={{ height: `${height}em`, background: `linear-gradient(90deg, ${c}, ${shade(c, 0.18)})`, color: on, display: "flex", alignItems: "center", gap: "2em", padding: "0 4em" }}>
        <ClinicMark data={data} design={design} size={height * 0.66} on={on} />
        <div style={{ fontSize: "3.1em", fontWeight: 800, flex: 1, ...ellipsis }}>{data.clinic.name}</div>
        <div style={{ fontSize: "1.65em", fontWeight: 700, letterSpacing: "0.18em", opacity: 0.9, whiteSpace: "nowrap" }}>{title}</div>
      </div>
      <div style={{ height: "0.9em", background: tint(c, 0.55) }} />
    </>
  );

  if (side === "front") {
    return (
      <div style={{ position: "absolute", inset: 0, backgroundColor: "#fff", backgroundImage: security }}>
        {band(11, "CARNÉ DE MASCOTA")}
        <Paw color={alpha(c, 0.07)} size={34} style={{ position: "absolute", right: "-4em", bottom: "2em", transform: "rotate(-18deg)" }} />
        <div style={{ position: "absolute", left: "4em", top: "15em" }}>
          <Photo data={data} design={design} size={[22, 27]} ring="#ffffff" radius={2.2} />
        </div>
        <div style={{ position: "absolute", left: "30.5em", right: "4em", top: "14.6em" }}>
          <div style={{ fontSize: "5.6em", fontWeight: 800, color: dark, letterSpacing: "-0.01em", ...ellipsis }}>{data.pet.name}</div>
          <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", columnGap: "3em", rowGap: "2em", marginTop: "2em" }}>
            <Field label="Especie" value={data.pet.species} labelColor={tint(dark, 0.35)} valueColor="#1F2937" />
            <Field label="Raza" value={data.pet.breed ?? (data.pet.species ? "Criollo" : null)} labelColor={tint(dark, 0.35)} valueColor="#1F2937" />
            <Field label="Sexo" value={data.pet.sex} labelColor={tint(dark, 0.35)} valueColor="#1F2937" />
            <Field label="Nacimiento" value={birth(data)} labelColor={tint(dark, 0.35)} valueColor="#1F2937" />
          </div>
        </div>
        <div style={{ position: "absolute", left: 0, right: 0, bottom: 0, height: "7em", background: tint(c, 0.9), borderTop: `0.3em solid ${tint(c, 0.6)}`, display: "flex", alignItems: "center", justifyContent: "space-between", gap: "3em", padding: "0 4em" }}>
          <span style={{ fontSize: "2.1em", fontFamily: "ui-monospace, SFMono-Regular, Menlo, monospace", fontWeight: 700, color: dark, whiteSpace: "nowrap" }}>N.º {data.pet.code}</span>
          <span style={{ fontSize: "2em", color: "#344054", ...ellipsis }}>
            Tutor: <b>{data.owner.full_name}</b>
          </span>
        </div>
      </div>
    );
  }

  return (
    <div style={{ position: "absolute", inset: 0, backgroundColor: "#fff", backgroundImage: security }}>
      {band(8, "VACUNAS VERIFICADAS")}
      <div style={{ position: "absolute", left: "4em", top: "12.5em", display: "flex", flexDirection: "column", alignItems: "center", gap: "1em" }}>
        <Qr data={data} size={27} border={tint(c, 0.55)} />
        <div style={{ fontSize: "1.75em", fontWeight: 700, color: dark }}>Escanéame</div>
      </div>
      <div style={{ position: "absolute", left: "35em", right: "4em", top: "12.5em", bottom: "7.5em", display: "flex", flexDirection: "column", gap: "1.8em" }}>
        {design.show_vaccines ? (
          <>
            <div style={{ fontSize: "2.5em", fontWeight: 800, color: dark }}>Vacunas</div>
            <VaccineList data={data} design={design} accent={c} />
          </>
        ) : (
          <div style={{ fontSize: "2.2em", color: "#344054", lineHeight: 1.4 }}>
            Escanea el código para ver sus vacunas al día, verificadas por <b>{data.clinic.name}</b>.
          </div>
        )}
      </div>
      <div style={{ position: "absolute", left: "35em", right: "4em", bottom: "2.6em" }}>
        <Emergency data={data} design={design} color="#475467" />
      </div>
    </div>
  );
}

// ---------------------------------------------------------------- Moderno (degradado)

function Modern({ data, design, side }: { data: CardData; design: CardDesign; side: CardSide }) {
  const c = design.color;
  const deep = shade(c, 0.45);
  const on = onColor(shade(c, 0.2));
  const gradient = `linear-gradient(135deg, ${tint(c, 0.08)} 0%, ${c} 45%, ${deep} 100%)`;
  const soft = on === "#ffffff" ? "rgba(255,255,255,.16)" : "rgba(17,24,39,.08)";

  const circles = (
    <>
      <div style={{ position: "absolute", width: "46em", height: "46em", borderRadius: "50%", right: "-16em", top: "-20em", background: soft }} />
      <div style={{ position: "absolute", width: "26em", height: "26em", borderRadius: "50%", right: "6em", bottom: "-16em", background: soft }} />
    </>
  );

  if (side === "front") {
    return (
      <div style={{ position: "absolute", inset: 0, background: gradient, color: on }}>
        {circles}
        <Paw color={on === "#ffffff" ? "rgba(255,255,255,.09)" : "rgba(17,24,39,.06)"} size={30} style={{ position: "absolute", right: "2em", top: "8em", transform: "rotate(16deg)" }} />
        <div style={{ position: "absolute", left: "4.5em", right: "4.5em", top: "3.8em", display: "flex", alignItems: "center", gap: "1.6em" }}>
          <span style={{ fontSize: "1.6em", fontWeight: 800, letterSpacing: "0.16em", background: soft, padding: "0.5em 1.1em", borderRadius: "99em", whiteSpace: "nowrap" }}>CARNÉ DE MASCOTA</span>
          <span style={{ flex: 1 }} />
          <span style={{ fontSize: "2em", fontWeight: 700, opacity: 0.95, maxWidth: "26em", ...ellipsis }}>{data.clinic.name}</span>
          <ClinicMark data={data} design={design} size={6} on={on} />
        </div>
        <div style={{ position: "absolute", left: "4.5em", top: "13.5em" }}>
          <Photo data={data} design={design} size={[26, 26]} round ring="#ffffff" />
        </div>
        <div style={{ position: "absolute", left: "35em", right: "4.5em", top: "14.2em" }}>
          <div style={{ fontSize: "7.2em", fontWeight: 800, letterSpacing: "-0.02em", lineHeight: 1.05, ...ellipsis }}>{data.pet.name}</div>
          <div style={{ fontSize: "2.35em", opacity: 0.9, marginTop: "0.5em", ...ellipsis }}>{[data.pet.species, data.pet.breed, data.pet.sex].filter(Boolean).join(" · ")}</div>
          <div style={{ display: "flex", gap: "1.6em", marginTop: "2.6em" }}>
            {[
              ["Nacimiento", birth(data)],
              ["Color", data.pet.color],
              ["Microchip", data.pet.microchip ? "Sí" : null],
            ]
              .filter(([, value]) => value)
              .slice(0, 2)
              .map(([label, value]) => (
              <div key={label} style={{ background: soft, borderRadius: "1.6em", padding: "1em 1.6em", minWidth: 0, flex: 1 }}>
                <div style={{ fontSize: "1.45em", letterSpacing: "0.08em", textTransform: "uppercase", opacity: 0.8, fontWeight: 700 }}>{label}</div>
                <div style={{ fontSize: "2.2em", fontWeight: 800, ...ellipsis }}>{value || "—"}</div>
              </div>
            ))}
          </div>
        </div>
        <div style={{ position: "absolute", left: "4.5em", right: "4.5em", bottom: "3.2em", display: "flex", justifyContent: "space-between", gap: "3em", fontSize: "1.9em", opacity: 0.92 }}>
          <span style={{ fontFamily: "ui-monospace, SFMono-Regular, Menlo, monospace", fontWeight: 700, whiteSpace: "nowrap" }}>N.º {data.pet.code}</span>
          <span style={ellipsis}>Tutor: {data.owner.full_name}</span>
        </div>
      </div>
    );
  }

  return (
    <div style={{ position: "absolute", inset: 0, background: "#fff" }}>
      <div style={{ position: "absolute", left: 0, top: 0, bottom: 0, width: "36em", background: gradient, color: on, overflow: "hidden" }}>
        {circles}
        <div style={{ position: "absolute", inset: 0, display: "flex", flexDirection: "column", alignItems: "center", justifyContent: "center", gap: "1.6em" }}>
          <Qr data={data} size={26} border="#ffffff" />
          <div style={{ fontSize: "1.9em", fontWeight: 800, letterSpacing: "0.04em" }}>Escanéame</div>
        </div>
      </div>
      <div style={{ position: "absolute", left: "40em", right: "4.5em", top: "5em", bottom: "4em", display: "flex", flexDirection: "column", gap: "1.8em" }}>
        <div>
          <div style={{ fontSize: "1.6em", fontWeight: 800, letterSpacing: "0.14em", color: ink(c) }}>VACUNAS VERIFICADAS</div>
          <div style={{ fontSize: "1.8em", color: "#475467", ...ellipsis }}>por {data.clinic.name}</div>
        </div>
        {design.show_vaccines ? (
          <VaccineList data={data} design={design} accent={c} />
        ) : (
          <div style={{ fontSize: "2.1em", color: "#344054", lineHeight: 1.4 }}>Escanea el código para ver sus vacunas al día.</div>
        )}
        <div style={{ marginTop: "auto" }}>
          <Emergency data={data} design={design} color="#475467" />
        </div>
      </div>
    </div>
  );
}

// ---------------------------------------------------------------- Huellitas (alegre)

function Paws({ data, design, side }: { data: CardData; design: CardDesign; side: CardSide }) {
  const c = design.color;
  const dark = ink(c);
  const bg = tint(c, 0.87);
  const pattern = pawPattern(c, 0.13);
  const on = onColor(c);

  const wave = (
    <svg viewBox="0 0 856 90" preserveAspectRatio="none" style={{ position: "absolute", left: 0, right: 0, bottom: 0, width: "100%", height: "10.5em" }} aria-hidden>
      <path d="M0 34 C 140 0, 260 62, 430 30 S 720 6, 856 36 L 856 90 L 0 90 Z" fill={c} />
    </svg>
  );

  if (side === "front") {
    return (
      <div style={{ position: "absolute", inset: 0, backgroundColor: bg, backgroundImage: pattern, backgroundSize: "16em 16em" }}>
        <div style={{ position: "absolute", right: "4em", top: "3.6em", display: "flex", alignItems: "center", gap: "1.2em", background: "#fff", borderRadius: "99em", padding: "0.6em 1.6em 0.6em 0.7em", maxWidth: "44em", boxShadow: "0 0.2em 0.6em rgba(16,24,40,.08)" }}>
          <ClinicMark data={data} design={design} size={4.6} on="#111827" />
          <span style={{ fontSize: "1.9em", fontWeight: 800, color: dark, ...ellipsis }}>{data.clinic.name}</span>
        </div>
        <div style={{ position: "absolute", left: "5em", top: "9.5em" }}>
          <Photo data={data} design={design} size={[27, 27]} round ring={c} ringStyle="dashed" />
        </div>
        <div style={{ position: "absolute", left: "36em", right: "4em", top: "11.5em" }}>
          <div style={{ fontSize: "2.4em", fontWeight: 700, color: dark, opacity: 0.85 }}>¡Hola! Soy</div>
          <div style={{ fontSize: "7.6em", fontWeight: 800, color: dark, letterSpacing: "-0.02em", lineHeight: 1, ...ellipsis }}>{data.pet.name}</div>
          <div style={{ fontSize: "2.3em", color: "#344054", marginTop: "0.7em", ...ellipsis }}>{[data.pet.species, data.pet.breed].filter(Boolean).join(" · ")}</div>
          <div style={{ display: "flex", gap: "1.4em", marginTop: "2.2em" }}>
            {[data.pet.sex, birth(data)].filter(Boolean).map((v) => (
              <span key={v} style={{ fontSize: "2em", fontWeight: 700, background: "#fff", color: dark, borderRadius: "99em", padding: "0.5em 1.2em", whiteSpace: "nowrap" }}>
                {v}
              </span>
            ))}
          </div>
        </div>
        {wave}
        <div style={{ position: "absolute", left: "5em", right: "5em", bottom: "2.4em", display: "flex", justifyContent: "space-between", gap: "3em", color: on, fontSize: "1.95em" }}>
          <span style={{ fontFamily: "ui-monospace, SFMono-Regular, Menlo, monospace", fontWeight: 700, whiteSpace: "nowrap" }}>N.º {data.pet.code}</span>
          <span style={ellipsis}>
            Mi tutor: <b>{data.owner.full_name}</b>
          </span>
        </div>
      </div>
    );
  }

  return (
    <div style={{ position: "absolute", inset: 0, backgroundColor: bg, backgroundImage: pattern, backgroundSize: "16em 16em" }}>
      <div style={{ position: "absolute", left: "4.5em", top: "5em", display: "flex", flexDirection: "column", alignItems: "center", gap: "1em" }}>
        <div style={{ borderRadius: "3em", border: `0.6em dashed ${c}`, padding: "0.8em", background: "#fff" }}>
          <Qr data={data} size={25} border="#ffffff" />
        </div>
        <div style={{ fontSize: "1.9em", fontWeight: 800, color: dark }}>¡Escanéame!</div>
      </div>
      <div style={{ position: "absolute", left: "38em", right: "4em", top: "5em", bottom: "4em", background: "#fff", borderRadius: "3em", padding: "2.4em 2.6em", display: "flex", flexDirection: "column", gap: "1.6em", boxShadow: "0 0.3em 1em rgba(16,24,40,.08)" }}>
        <div style={{ fontSize: "2.4em", fontWeight: 800, color: dark }}>Mis vacunas</div>
        {design.show_vaccines ? (
          <VaccineList data={data} design={design} accent={c} />
        ) : (
          <div style={{ fontSize: "2em", color: "#344054", lineHeight: 1.4 }}>Escanea el código y mira que estoy al día.</div>
        )}
        <div style={{ marginTop: "auto" }}>
          <Emergency data={data} design={design} color="#475467" />
        </div>
      </div>
    </div>
  );
}
