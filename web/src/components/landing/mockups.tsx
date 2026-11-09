import { LogoMark } from "@/components/brand/Logo";
import { cn } from "@/utils";

/**
 * Product pictures for the landing, drawn in HTML so they stay sharp, light
 * and in sync with the real screens (agenda, WhatsApp, consent, pet card).
 */

const AGENDA = [
  { time: "8:00", pet: "Rocky", owner: "Paola Díaz", service: "Vacunación", status: "Confirmada", tone: "bg-brand-50 text-brand-700" },
  { time: "9:30", pet: "Michi", owner: "Andrés Gómez", service: "Consulta general", status: "En sala", tone: "bg-sky-50 text-sky-700" },
  { time: "10:30", pet: "Luna", owner: "Camila Ruiz", service: "Control post-cirugía", status: "Por confirmar", tone: "bg-amber-50 text-amber-700" },
  { time: "11:00", pet: "Toby", owner: "Jorge Pérez", service: "Peluquería", status: "Confirmada", tone: "bg-brand-50 text-brand-700" },
];

const PET_COLORS = ["bg-amber-200 text-amber-900", "bg-sky-200 text-sky-900", "bg-rose-200 text-rose-900", "bg-violet-200 text-violet-900"];

export function AppWindow({ className }: { className?: string }) {
  return (
    <div className={cn("overflow-hidden rounded-2xl border border-white/10 bg-white shadow-2xl shadow-black/40", className)}>
      <div className="flex items-center gap-1.5 border-b border-gray-100 bg-gray-50 px-4 py-2.5">
        <span className="size-2.5 rounded-full bg-gray-300" />
        <span className="size-2.5 rounded-full bg-gray-300" />
        <span className="size-2.5 rounded-full bg-gray-300" />
        <span className="ml-3 rounded-md bg-white px-3 py-0.5 text-[10px] text-gray-400">zooetia.co/app</span>
      </div>
      <div className="flex">
        <aside className="hidden w-36 shrink-0 border-r border-gray-100 p-3 sm:block">
          <div className="mb-4 flex items-center gap-1.5">
            <LogoMark className="size-5" />
            <span className="text-xs font-bold text-gray-900">Zooetia</span>
          </div>
          {["Hoy", "Agenda", "Tutores", "Tareas", "WhatsApp"].map((item, i) => (
            <div key={item} className={cn("mb-1 rounded-md px-2 py-1.5 text-[11px]", i === 0 ? "bg-brand-50 font-semibold text-brand-700" : "text-gray-500")}>
              {item}
            </div>
          ))}
        </aside>
        <div className="min-w-0 flex-1 p-4">
          <p className="text-sm font-semibold text-gray-900">Buenos días, Carolina</p>
          <p className="mb-3 text-[11px] text-gray-500">Martes, 14 de octubre · Veterinaria Anipet</p>
          <div className="mb-3 grid grid-cols-3 gap-2">
            {[
              ["Citas hoy", "12"],
              ["Por confirmar", "2"],
              ["En sala", "1"],
            ].map(([label, value]) => (
              <div key={label} className="rounded-lg border border-gray-100 p-2">
                <p className="text-[10px] text-gray-500">{label}</p>
                <p className="text-base font-semibold text-gray-900">{value}</p>
              </div>
            ))}
          </div>
          <div className="space-y-1.5">
            {AGENDA.map((a, i) => (
              <div key={a.time} className="flex items-center gap-2.5 rounded-lg border border-gray-100 px-2.5 py-2">
                <span className="w-9 text-[11px] font-semibold text-gray-700">{a.time}</span>
                <span className={cn("flex size-6 shrink-0 items-center justify-center rounded-full text-[10px] font-bold", PET_COLORS[i])}>{a.pet[0]}</span>
                <span className="min-w-0 flex-1">
                  <span className="block truncate text-[11px] font-semibold text-gray-900">
                    {a.pet} <span className="font-normal text-gray-500">· {a.owner}</span>
                  </span>
                  <span className="block truncate text-[10px] text-gray-500">{a.service}</span>
                </span>
                <span className={cn("hidden rounded-full px-2 py-0.5 text-[10px] font-medium sm:inline", a.tone)}>{a.status}</span>
              </div>
            ))}
          </div>
        </div>
      </div>
    </div>
  );
}

export function WhatsAppBubble({ className, compact = false }: { className?: string; compact?: boolean }) {
  return (
    <div className={cn("w-64 overflow-hidden rounded-2xl bg-[#efeae2] shadow-2xl shadow-black/30", className)}>
      <div className="flex items-center gap-2 bg-[#075e54] px-3 py-2 text-white">
        <LogoMark className="size-6" />
        <div className="leading-tight">
          <p className="text-[11px] font-semibold">Veterinaria Anipet</p>
          <p className="text-[9px] opacity-80">Cuenta de empresa</p>
        </div>
      </div>
      <div className="space-y-2 p-2.5">
        <div className="rounded-lg rounded-tl-none bg-white p-2.5 text-[11px] leading-snug text-gray-800 shadow-sm">
          Hola Paola 👋 Te recordamos la cita de <b>Rocky</b> mañana a las <b>8:00 a. m.</b> (vacunación).
          <span className="mt-1 block text-right text-[9px] text-gray-400">6:02 p. m.</span>
        </div>
        <div className="grid grid-cols-2 gap-1.5">
          <span className="rounded-lg bg-white py-1.5 text-center text-[11px] font-medium text-sky-600 shadow-sm">✅ Confirmo</span>
          <span className="rounded-lg bg-white py-1.5 text-center text-[11px] font-medium text-sky-600 shadow-sm">Reprogramar</span>
        </div>
        {!compact && (
          <>
            <div className="ml-auto w-fit rounded-lg rounded-tr-none bg-[#d9fdd3] px-2.5 py-1.5 text-[11px] text-gray-800 shadow-sm">✅ Confirmo</div>
            <div className="rounded-lg rounded-tl-none bg-white p-2.5 text-[11px] leading-snug text-gray-800 shadow-sm">
              ¡Listo! La cita de Rocky quedó <b>confirmada</b>. Te esperamos 🐾
            </div>
          </>
        )}
      </div>
    </div>
  );
}

export function PetCardMock({ className }: { className?: string }) {
  return (
    <div className={cn("w-72 overflow-hidden rounded-2xl bg-white shadow-2xl shadow-black/25", className)}>
      <div className="flex items-center justify-between bg-brand-600 px-4 py-2.5 text-white">
        <span className="text-[11px] font-semibold tracking-wide">CARNÉ DE VACUNACIÓN</span>
        <LogoMark className="size-5" />
      </div>
      <div className="flex gap-3 p-4">
        <div className="flex size-16 shrink-0 items-center justify-center rounded-xl bg-amber-100 text-3xl">🐶</div>
        <div className="min-w-0 flex-1 text-[11px] text-gray-600">
          <p className="text-base font-bold text-gray-900">Rocky</p>
          <p>Golden Retriever · Macho</p>
          <p>3 años · Microchip 9851…</p>
        </div>
        <Qr className="size-14 shrink-0" />
      </div>
      <div className="space-y-1 border-t border-gray-100 px-4 py-3 text-[11px]">
        {[
          ["Antirrábica", "12/03/2026", "Vigente"],
          ["Múltiple canina", "02/02/2026", "Vigente"],
          ["Desparasitación", "05/09/2026", "Vigente"],
        ].map(([name, date, state]) => (
          <div key={name} className="flex items-center justify-between">
            <span className="font-medium text-gray-800">{name}</span>
            <span className="text-gray-500">{date}</span>
            <span className="rounded-full bg-brand-50 px-1.5 text-[10px] font-medium text-brand-700">{state}</span>
          </div>
        ))}
      </div>
    </div>
  );
}

/** Decorative QR-like pattern (not scannable). */
function Qr({ className }: { className?: string }) {
  const cells = "1110111010110100101111101010011100010111101101001011001111010011110101101".split("");
  return (
    <svg viewBox="0 0 9 9" className={className} aria-hidden shapeRendering="crispEdges">
      <rect width="9" height="9" fill="#fff" />
      {cells.map((c, i) => (c === "1" ? <rect key={i} x={i % 9} y={Math.floor(i / 9)} width="1" height="1" fill="#0b3019" /> : null))}
    </svg>
  );
}

export function ConsentMock({ className }: { className?: string }) {
  return (
    <div className={cn("w-full max-w-md rounded-xl bg-white p-6 text-[11px] leading-relaxed text-gray-700 shadow-2xl shadow-black/20", className)} style={{ fontFamily: '"DejaVu Sans", Verdana, sans-serif' }}>
      <div className="mb-3 flex items-center justify-between border-b-2 border-brand-500 pb-2">
        <div className="flex items-center gap-2">
          <LogoMark className="size-6" />
          <div className="leading-tight">
            <p className="text-xs font-bold text-gray-900">Veterinaria Anipet</p>
            <p className="text-[9px] text-gray-500">NIT 900.123.456-7</p>
          </div>
        </div>
        <span className="text-[9px] text-gray-400">Documento n.º 0042</span>
      </div>
      <p className="mb-2 text-center text-xs font-bold tracking-wide text-gray-900">CONSENTIMIENTO PARA CIRUGÍA Y ANESTESIA</p>
      <p className="mb-2 text-justify">
        Yo, <mark className="rounded bg-brand-50 px-0.5 text-gray-800">Paola Andrea Díaz</mark>, identificada con C.C. <mark className="rounded bg-brand-50 px-0.5 text-gray-800">1.098.765.432</mark>,
        autorizo al equipo médico a realizar a <mark className="rounded bg-brand-50 px-0.5 text-gray-800">Rocky</mark> el procedimiento de{" "}
        <mark className="rounded bg-amber-50 px-0.5 text-amber-900">orquiectomía</mark>. Me explicaron los riesgos de la anestesia y…
      </p>
      <div className="mt-4 grid grid-cols-2 gap-6">
        <div>
          <svg viewBox="0 0 160 50" className="h-10 w-full" aria-hidden>
            <path
              d="M6 34c10-18 18-24 22-16s-8 22-2 20 14-26 22-24-4 22 4 20 10-14 16-12 0 10 8 8 14-10 22-8 10 6 18 2 14-8 22-6"
              fill="none"
              stroke="#0b3019"
              strokeWidth="2"
              strokeLinecap="round"
              className="motion-safe:animate-[sign_2.6s_ease-in-out_infinite]"
              strokeDasharray="420"
            />
          </svg>
          <p className="border-t border-gray-400 pt-1 text-[10px]">
            <b>Paola Andrea Díaz</b>
            <br />
            Tutor · firmó en pantalla
          </p>
        </div>
        <div>
          <svg viewBox="0 0 160 50" className="h-10 w-full" aria-hidden>
            <path d="M10 30c8-14 16-18 20-10s-4 16 4 12 12-20 20-16-2 14 8 12 18-12 30-8 20 4 34-2" fill="none" stroke="#0b3019" strokeWidth="2" strokeLinecap="round" />
          </svg>
          <p className="border-t border-gray-400 pt-1 text-[10px]">
            <b>Dra. Carolina Restrepo</b>
            <br />
            Médica veterinaria · T.P. 12345
          </p>
        </div>
      </div>
    </div>
  );
}
