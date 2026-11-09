import { Logo, LogoMark } from "@/components/brand/Logo";
import {
  ArrowIcon,
  CalendarIcon,
  CardIcon,
  ChatIcon,
  CheckIcon,
  ClipboardIcon,
  PenIcon,
  PortalIcon,
  ShieldIcon,
  TasksIcon,
  TeamIcon,
} from "@/components/landing/icons";
import { AppWindow, ConsentMock, PetCardMock, WhatsAppBubble } from "@/components/landing/mockups";
import { apiUrl } from "@/lib/server/laravel";
import { formatCop } from "@/lib/settings";
import { cn } from "@/utils";
import type { Metadata } from "next";
import { cookies } from "next/headers";
import Link from "next/link";

export const metadata: Metadata = {
  title: { absolute: "Zooetia · Software para clínicas veterinarias en Colombia" },
  description:
    "Agenda, historia clínica, recordatorios y confirmaciones por WhatsApp, consentimientos firmados en pantalla, carné digital con QR y portal para los tutores. Prueba 15 días gratis, sin tarjeta.",
};

type Plan = {
  code: string;
  name: string;
  price_monthly: number;
  features: { key: string; label: string; soon: boolean; included: boolean }[];
  limits: { branches: number | null; whatsapp_messages: number; dian_documents: number };
};

/** Prices come from the database (same catalog as Plan y facturación), refreshed every 10 minutes. */
async function plans(): Promise<Plan[]> {
  try {
    const response = await fetch(apiUrl("public/plans"), { headers: { Accept: "application/json" }, next: { revalidate: 600 } });
    return response.ok ? ((await response.json()).data as Plan[]) : [];
  } catch {
    return [];
  }
}

const NAV = [
  ["Funciones", "#funciones"],
  ["WhatsApp", "#whatsapp"],
  ["Precios", "#precios"],
  ["Preguntas", "#preguntas"],
] as const;

const FEATURES = [
  { icon: CalendarIcon, title: "Agenda inteligente", text: "Por sede y profesional, con horarios, bloqueos, sala de espera y horas libres sugeridas. Sin cruces de citas." },
  { icon: ClipboardIcon, title: "Historia clínica completa", text: "Consultas, vacunas, desparasitaciones, fórmulas y adjuntos. Se cierra con firma del veterinario y se exporta en PDF." },
  { icon: ChatIcon, title: "Recordatorios por WhatsApp", text: "Citas, refuerzos de vacuna y controles. El tutor confirma o reprograma con un botón y la agenda se actualiza sola." },
  { icon: PenIcon, title: "Consentimientos firmados", text: "Cirugía, anestesia, eutanasia, hospitalización. Se firman en la pantalla o a distancia con un enlace, con evidencia legal." },
  { icon: CardIcon, title: "Carné digital con QR", text: "Con el logo de tu clínica. Si la mascota se pierde, quien la encuentre escanea el QR y contacta al tutor." },
  { icon: PortalIcon, title: "Portal del tutor", text: "Cada tutor ve sus mascotas, vacunas, próximas citas y documentos desde el celular. Tu clínica, siempre a la mano." },
  { icon: TasksIcon, title: "Reserva en línea", text: "Tu enlace y tu QR para que pidan cita a cualquier hora. Tú decides: cada solicitud la aceptas o la rechazas." },
  { icon: TeamIcon, title: "Equipo, sedes y tareas", text: "Administrador, veterinario y recepción: cada quien ve solo lo suyo. Varias sedes y tareas asignadas al equipo." },
];

const FAQ = [
  ["¿Cómo funciona la prueba gratis?", "Creas la cuenta de tu clínica en un minuto y tienes 15 días con todas las funciones del plan Pro. No pedimos tarjeta. Al terminar eliges un plan; si no lo haces, tu información sigue ahí, en modo consulta."],
  ["¿Necesito instalar algo?", "No. Zooetia funciona en el navegador del computador, la tableta o el celular. Tu equipo entra con su correo y contraseña desde cualquier lugar."],
  ["¿Mis datos y los de mis clientes están seguros?", "Cada clínica solo ve su propia información, con copias de seguridad diarias. Zooetia está pensado para cumplir la Ley 1581 de datos personales: registra la autorización de cada tutor y para qué canales la dio."],
  ["¿Los consentimientos firmados en pantalla tienen validez?", "Sí. Son firmas electrónicas según la Ley 527 de 1999: guardamos el trazo, la fecha y hora, el dispositivo y una huella del documento que impide cambiarlo después. Cada documento queda en PDF con una página de evidencias."],
  ["¿Cuánto cuestan los mensajes de WhatsApp?", "Los mensajes incluidos en tu plan ya están en el precio. Usamos la API oficial de WhatsApp de Meta; las respuestas del bot a los tutores no tienen costo adicional."],
  ["¿Mis clientes pueden pedir cita en línea?", "Sí, desde el plan Pro. Compartes tu enlace o tu QR; el tutor elige servicio, día y hora, y la solicitud te llega al instante. Nada queda en firme hasta que tu equipo la acepta, y el tutor recibe la respuesta por WhatsApp."],
  ["¿Puedo cambiar de plan o cancelar?", "Sí, cuando quieras. El pago es mes a mes, sin permanencia mínima."],
  ["¿Me ayudan a empezar?", "Sí. Al entrar verás una guía de primeros pasos y nuestro equipo te acompaña por WhatsApp para dejar tu clínica lista."],
] as const;

export default async function Landing() {
  const [catalog, store] = await Promise.all([plans(), cookies()]);
  const loggedIn = store.has("zoo_session");

  return (
    <div className="bg-white text-gray-800 [scroll-behavior:smooth]">
      {/* ---------- Header ---------- */}
      <header className="sticky top-0 z-50 border-b border-white/10 bg-brand-950/85 backdrop-blur-md">
        <div className="mx-auto flex h-16 max-w-7xl items-center gap-6 px-4 sm:px-6">
          <Link href="/" aria-label="Zooetia, inicio">
            <Logo inverted />
          </Link>
          <nav className="ml-6 hidden gap-7 text-sm text-white/75 md:flex" aria-label="Secciones">
            {NAV.map(([label, href]) => (
              <a key={href} href={href} className="transition hover:text-white">
                {label}
              </a>
            ))}
          </nav>
          <div className="ml-auto flex items-center gap-2 sm:gap-3">
            {loggedIn ? (
              <Link href="/app" className="rounded-lg bg-white px-4 py-2 text-sm font-semibold text-brand-800 transition hover:bg-brand-50">
                Ir a mi clínica
              </Link>
            ) : (
              <>
                <Link href="/login" className="hidden rounded-lg px-3 py-2 text-sm font-medium text-white/85 transition hover:text-white sm:inline">
                  Ingresar
                </Link>
                <Link href="/registro" className="rounded-lg bg-white px-4 py-2 text-sm font-semibold text-brand-800 transition hover:bg-brand-50">
                  Prueba gratis
                </Link>
              </>
            )}
            <details className="group relative md:hidden">
              <summary className="flex size-10 cursor-pointer list-none items-center justify-center rounded-lg text-white hover:bg-white/10 [&::-webkit-details-marker]:hidden" aria-label="Menú">
                <svg viewBox="0 0 24 24" className="size-6" fill="none" stroke="currentColor" strokeWidth={2} strokeLinecap="round" aria-hidden>
                  <path d="M4 7h16M4 12h16M4 17h16" />
                </svg>
              </summary>
              <div className="absolute right-0 mt-2 w-52 rounded-xl border border-gray-100 bg-white p-2 shadow-xl">
                {NAV.map(([label, href]) => (
                  <a key={href} href={href} className="block rounded-lg px-3 py-2 text-sm text-gray-700 hover:bg-gray-50">
                    {label}
                  </a>
                ))}
                {!loggedIn && (
                  <Link href="/login" className="block rounded-lg px-3 py-2 text-sm font-medium text-brand-700 hover:bg-gray-50">
                    Ingresar
                  </Link>
                )}
              </div>
            </details>
          </div>
        </div>
      </header>

      <main>
        {/* ---------- Hero ---------- */}
        <section className="relative -mt-16 overflow-hidden bg-brand-950 pt-16 text-white">
          <div aria-hidden className="pointer-events-none absolute inset-0 bg-[linear-gradient(rgba(255,255,255,.05)_1px,transparent_1px),linear-gradient(90deg,rgba(255,255,255,.05)_1px,transparent_1px)] bg-[size:56px_56px] [mask-image:radial-gradient(ellipse_at_70%_40%,black,transparent_70%)]" />
          <div aria-hidden className="pointer-events-none absolute -top-40 right-[-10%] size-[640px] rounded-full bg-brand-500/40 blur-[120px]" />
          <div aria-hidden className="pointer-events-none absolute bottom-[-30%] left-[-10%] size-[520px] rounded-full bg-emerald-400/15 blur-[120px]" />

          <div className="relative mx-auto grid max-w-7xl items-center gap-14 px-4 pt-16 pb-24 sm:px-6 lg:grid-cols-[1.05fr_1fr] lg:pt-24 lg:pb-32">
            <div className="motion-safe:animate-[fade-up_.7s_ease-out_both]">
              <p className="mb-5 inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/5 px-3 py-1 text-xs font-medium text-brand-100">
                <span className="size-1.5 rounded-full bg-brand-300 motion-safe:animate-pulse" />
                Hecho en Colombia para clínicas veterinarias
              </p>
              <h1 className="text-4xl leading-[1.08] font-bold tracking-tight sm:text-5xl lg:text-6xl">
                Tu clínica veterinaria,{" "}
                <span className="bg-gradient-to-r from-brand-200 via-emerald-200 to-brand-300 bg-clip-text text-transparent">organizada y sin papeles.</span>
              </h1>
              <p className="mt-6 max-w-xl text-lg leading-relaxed text-white/75">
                Agenda, historia clínica, recordatorios por WhatsApp que se confirman solos, consentimientos firmados en pantalla y un portal para tus clientes. Todo en un solo lugar.
              </p>
              <div className="mt-9 flex flex-wrap gap-3">
                <Link
                  href={loggedIn ? "/app" : "/registro"}
                  className="group inline-flex items-center gap-2 rounded-xl bg-white px-6 py-3.5 text-base font-semibold text-brand-800 shadow-lg shadow-black/20 transition hover:-translate-y-0.5 hover:bg-brand-50"
                >
                  {loggedIn ? "Ir a mi clínica" : "Empieza gratis 15 días"}
                  <ArrowIcon className="size-5 transition group-hover:translate-x-0.5" />
                </Link>
                <a href="#precios" className="inline-flex items-center rounded-xl border border-white/20 px-6 py-3.5 text-base font-semibold text-white transition hover:bg-white/10">
                  Ver precios
                </a>
              </div>
              <ul className="mt-8 flex flex-wrap gap-x-6 gap-y-2 text-sm text-white/70">
                {["Sin tarjeta de crédito", "Lista en minutos", "Sin permanencia"].map((t) => (
                  <li key={t} className="flex items-center gap-2">
                    <svg viewBox="0 0 20 20" className="size-4 text-brand-300" fill="none" stroke="currentColor" strokeWidth={2.4} strokeLinecap="round" strokeLinejoin="round" aria-hidden>
                      <path d="m5 10.5 3.2 3L15 6.5" />
                    </svg>
                    {t}
                  </li>
                ))}
              </ul>
            </div>

            <div className="relative mx-auto w-full max-w-xl motion-safe:animate-[fade-up_.9s_.15s_ease-out_both] lg:mx-0">
              <AppWindow className="lg:translate-x-6" />
              <WhatsAppBubble compact className="absolute -bottom-12 -left-4 hidden scale-90 sm:block motion-safe:animate-[float_7s_ease-in-out_infinite] lg:-left-14" />
              <div className="absolute -top-6 -right-3 hidden items-center gap-2 rounded-xl bg-white px-3 py-2 text-xs font-medium text-gray-800 shadow-xl sm:flex motion-safe:animate-[float_6s_1s_ease-in-out_infinite]">
                <span className="flex size-6 items-center justify-center rounded-full bg-brand-100 text-brand-700">✓</span>
                Rocky confirmó su cita
              </div>
            </div>
          </div>
        </section>

        {/* ---------- Trust strip ---------- */}
        <section className="border-b border-gray-100 bg-gray-50/60">
          <div className="mx-auto grid max-w-7xl gap-6 px-4 py-8 text-sm text-gray-600 sm:grid-cols-2 sm:px-6 lg:grid-cols-4">
            {[
              ["Ley 1581", "Autorización de datos de cada tutor, por canal"],
              ["Ley 527", "Firma electrónica con evidencia y PDF"],
              ["WhatsApp oficial", "API de Meta: mensajes que sí llegan"],
              ["En la nube", "Copias diarias, desde cualquier dispositivo"],
            ].map(([title, text]) => (
              <div key={title} className="flex items-start gap-3">
                <ShieldIcon className="mt-0.5 size-5 shrink-0 text-brand-500" />
                <p>
                  <b className="text-gray-900">{title}.</b> {text}
                </p>
              </div>
            ))}
          </div>
        </section>

        {/* ---------- Features ---------- */}
        <section id="funciones" className="scroll-mt-20 py-24">
          <div className="mx-auto max-w-7xl px-4 sm:px-6">
            <SectionTitle eyebrow="Todo en un solo lugar" title="Lo que tu clínica necesita, sin complicaciones" text="Diseñado con veterinarios: cada pantalla resuelve una tarea del día a día, rápido y desde cualquier dispositivo." />
            <div className="mt-14 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
              {FEATURES.map(({ icon: Icon, title, text }) => (
                <div key={title} className="group rounded-2xl border border-gray-100 bg-white p-6 transition hover:-translate-y-1 hover:border-brand-200 hover:shadow-xl hover:shadow-brand-900/5">
                  <span className="mb-5 flex size-12 items-center justify-center rounded-xl bg-brand-50 text-brand-600 transition group-hover:bg-brand-500 group-hover:text-white">
                    <Icon />
                  </span>
                  <h3 className="mb-2 text-base font-semibold text-gray-900">{title}</h3>
                  <p className="text-sm leading-relaxed text-gray-600">{text}</p>
                </div>
              ))}
            </div>
          </div>
        </section>

        {/* ---------- Spotlight: WhatsApp ---------- */}
        <section id="whatsapp" className="scroll-mt-20 overflow-hidden bg-gradient-to-b from-brand-25 to-white py-24">
          <div className="mx-auto grid max-w-7xl items-center gap-14 px-4 sm:px-6 lg:grid-cols-2">
            <div>
              <Eyebrow>Menos inasistencias</Eyebrow>
              <h2 className="text-3xl font-bold tracking-tight text-gray-900 sm:text-4xl">Las citas se confirman solas por WhatsApp</h2>
              <p className="mt-5 text-lg leading-relaxed text-gray-600">
                Zooetia le recuerda la cita al tutor el día anterior. Con un botón confirma o pide reprogramar, y tu agenda se actualiza sin que nadie tenga que llamar.
              </p>
              <Bullets
                items={[
                  "Recordatorios de citas, refuerzos de vacuna y controles",
                  "Confirmar, reprogramar o cancelar con un toque",
                  "Si ya tiene cita agendada, no le llega un recordatorio de más",
                  "Solo a quienes autorizaron WhatsApp (Ley 1581)",
                ]}
              />
            </div>
            <div className="relative flex justify-center">
              <div aria-hidden className="absolute inset-10 rounded-full bg-brand-200/50 blur-3xl" />
              <WhatsAppBubble className="relative w-80 rotate-[-2deg] motion-safe:animate-[float_8s_ease-in-out_infinite]" />
            </div>
          </div>
        </section>

        {/* ---------- Spotlight: consents ---------- */}
        <section className="overflow-hidden py-24">
          <div className="mx-auto grid max-w-7xl items-center gap-14 px-4 sm:px-6 lg:grid-cols-2">
            <div className="relative order-last flex justify-center lg:order-first">
              <div aria-hidden className="absolute inset-6 rounded-[40px] bg-gradient-to-br from-brand-100 to-amber-50" />
              <ConsentMock className="relative my-10 rotate-[1.5deg]" />
            </div>
            <div>
              <Eyebrow>Adiós a las carpetas</Eyebrow>
              <h2 className="text-3xl font-bold tracking-tight text-gray-900 sm:text-4xl">Consentimientos firmados en pantalla, con validez legal</h2>
              <p className="mt-5 text-lg leading-relaxed text-gray-600">
                Eliges el tipo de documento, llenas los datos del caso y el tutor firma con el dedo en la tableta o el celular. Si no está en la clínica, le envías un enlace para firmar a distancia.
              </p>
              <Bullets
                items={[
                  "Cirugía y anestesia, eutanasia, hospitalización, alta voluntaria y más",
                  "Los textos se adaptan a tu clínica, editándolos como en Word",
                  "PDF con página de evidencias: fecha, dispositivo y huella del documento",
                  "Queda en la ficha de la mascota y en el portal del tutor",
                ]}
              />
            </div>
          </div>
        </section>

        {/* ---------- Spotlight: card & portal ---------- */}
        <section className="relative overflow-hidden bg-brand-950 py-24 text-white">
          <div aria-hidden className="pointer-events-none absolute -right-40 -bottom-40 size-[520px] rounded-full bg-brand-500/30 blur-[120px]" />
          <div className="relative mx-auto grid max-w-7xl items-center gap-14 px-4 sm:px-6 lg:grid-cols-2">
            <div>
              <Eyebrow dark>Tus clientes, conectados</Eyebrow>
              <h2 className="text-3xl font-bold tracking-tight sm:text-4xl">Carné digital y portal del tutor con la marca de tu clínica</h2>
              <p className="mt-5 text-lg leading-relaxed text-white/70">
                Cada mascota tiene su carné con QR y el logo de tu clínica. Los tutores entran al portal a ver vacunas, próximas citas y documentos, y firman desde ahí cuando hace falta.
              </p>
              <Bullets
                dark
                items={["Carné descargable e imprimible", "Modo “mascota perdida” con contacto directo al tutor", "Acceso del tutor por invitación de la clínica", "Tu logo en el carné, los PDF y los documentos"]}
              />
            </div>
            <div className="relative flex justify-center">
              <PetCardMock className="relative z-10 -rotate-3 motion-safe:animate-[float_7s_ease-in-out_infinite]" />
              <div className="absolute top-14 left-1/2 hidden w-56 translate-x-8 rotate-6 rounded-2xl border border-white/10 bg-white/10 p-4 backdrop-blur sm:block">
                <p className="text-xs text-white/60">Próxima cita</p>
                <p className="mt-1 font-semibold">Rocky · Vacunación</p>
                <p className="text-sm text-white/70">Mar 14 oct · 8:00 a. m.</p>
                <div className="mt-3 h-px bg-white/10" />
                <p className="mt-3 text-xs text-white/60">Documentos</p>
                <p className="mt-1 text-sm">Consentimiento de cirugía · <span className="text-brand-200">Firmado</span></p>
              </div>
            </div>
          </div>
        </section>

        {/* ---------- How to start ---------- */}
        <section className="py-24">
          <div className="mx-auto max-w-7xl px-4 sm:px-6">
            <SectionTitle eyebrow="Empieza hoy" title="Tu clínica lista en tres pasos" />
            <ol className="mt-14 grid gap-6 md:grid-cols-3">
              {[
                ["Crea tu cuenta", "Nombre de la clínica, tu correo y una contraseña. Sin tarjeta."],
                ["Configura lo básico", "Logo, horario, servicios e invita a tu equipo. Una guía te muestra qué falta."],
                ["Atiende", "Registra tutores y mascotas, agenda citas y deja que los recordatorios trabajen por ti."],
              ].map(([title, text], i) => (
                <li key={title} className="relative rounded-2xl border border-gray-100 p-7">
                  <span className="mb-5 flex size-10 items-center justify-center rounded-full bg-brand-500 text-base font-bold text-white">{i + 1}</span>
                  <h3 className="mb-2 text-lg font-semibold text-gray-900">{title}</h3>
                  <p className="text-sm leading-relaxed text-gray-600">{text}</p>
                </li>
              ))}
            </ol>
          </div>
        </section>

        {/* ---------- Pricing ---------- */}
        <section id="precios" className="scroll-mt-20 bg-gray-50 py-24">
          <div className="mx-auto max-w-7xl px-4 sm:px-6">
            <SectionTitle eyebrow="Precios" title="Planes simples, sin sorpresas" text="Precios mensuales en pesos colombianos. Todos empiezan con 15 días gratis del plan Pro y todos incluyen agenda, historia clínica, carné con QR, consentimientos firmados y portal del tutor." />
            {catalog.length > 0 ? (
              <div className="mx-auto mt-14 grid max-w-6xl gap-6 lg:grid-cols-3">
                {catalog.map((plan) => {
                  const featured = plan.code === "pro";
                  return (
                    <div
                      key={plan.code}
                      className={cn(
                        "relative flex flex-col rounded-3xl p-8",
                        featured ? "bg-brand-950 text-white shadow-2xl shadow-brand-900/30 lg:-my-4 lg:py-12" : "border border-gray-200 bg-white",
                      )}
                    >
                      {featured && <span className="absolute -top-3.5 left-8 rounded-full bg-brand-300 px-3 py-1 text-xs font-bold text-brand-950">El más elegido</span>}
                      <h3 className={cn("text-lg font-semibold", featured ? "text-white" : "text-gray-900")}>{plan.name}</h3>
                      <p className="mt-4 flex items-baseline gap-1">
                        <span className="text-4xl font-bold tracking-tight">{formatCop(plan.price_monthly)}</span>
                        <span className={cn("text-sm", featured ? "text-white/60" : "text-gray-500")}>/mes</span>
                      </p>
                      <ul className={cn("mt-8 flex-1 space-y-3 text-sm", featured ? "text-white/85" : "text-gray-700")}>
                        <PlanLine featured={featured} label={plan.limits.branches === null ? "Sedes ilimitadas" : `${plan.limits.branches} sede`} />
                        <PlanLine featured={featured} label="Equipo sin límite de usuarios" />
                        {plan.limits.whatsapp_messages > 0 && <PlanLine featured={featured} label={`${plan.limits.whatsapp_messages} mensajes de WhatsApp al mes`} />}
                        {plan.features.map((f) => (
                          <PlanLine key={f.key} featured={featured} label={f.label} included={f.included} soon={f.soon} />
                        ))}
                      </ul>
                      <Link
                        href="/registro"
                        className={cn(
                          "mt-9 rounded-xl px-5 py-3 text-center text-sm font-semibold transition",
                          featured ? "bg-white text-brand-800 hover:bg-brand-50" : "bg-brand-500 text-white hover:bg-brand-600",
                        )}
                      >
                        Probar gratis 15 días
                      </Link>
                    </div>
                  );
                })}
              </div>
            ) : (
              <p className="mt-10 text-center text-gray-600">
                <Link href="/registro" className="font-semibold text-brand-600 underline">
                  Crea tu cuenta gratis
                </Link>{" "}
                y conoce los planes desde la aplicación.
              </p>
            )}
          </div>
        </section>

        {/* ---------- FAQ ---------- */}
        <section id="preguntas" className="scroll-mt-20 py-24">
          <div className="mx-auto max-w-3xl px-4 sm:px-6">
            <SectionTitle eyebrow="Preguntas frecuentes" title="Resolvemos tus dudas" />
            <div className="mt-12 divide-y divide-gray-100 rounded-2xl border border-gray-100">
              {FAQ.map(([q, a]) => (
                <details key={q} className="group px-6 py-5 [&_summary::-webkit-details-marker]:hidden">
                  <summary className="flex cursor-pointer list-none items-center justify-between gap-4 text-base font-semibold text-gray-900">
                    {q}
                    <span className="flex size-7 shrink-0 items-center justify-center rounded-full bg-gray-100 text-gray-500 transition group-open:rotate-45 group-open:bg-brand-500 group-open:text-white" aria-hidden>
                      +
                    </span>
                  </summary>
                  <p className="mt-3 text-sm leading-relaxed text-gray-600">{a}</p>
                </details>
              ))}
            </div>
          </div>
        </section>

        {/* ---------- Final CTA ---------- */}
        <section className="px-4 pb-24 sm:px-6">
          <div className="relative mx-auto max-w-6xl overflow-hidden rounded-3xl bg-gradient-to-br from-brand-600 via-brand-700 to-brand-950 px-6 py-16 text-center text-white sm:px-16">
            <div aria-hidden className="absolute -top-24 -right-24 size-80 rounded-full bg-brand-300/30 blur-3xl" />
            <h2 className="relative text-3xl font-bold tracking-tight sm:text-4xl">Dale a tu clínica el orden que merece</h2>
            <p className="relative mx-auto mt-4 max-w-xl text-lg text-white/75">Empieza hoy con 15 días gratis. Sin tarjeta, sin instalar nada, sin permanencia.</p>
            <Link href="/registro" className="relative mt-8 inline-flex items-center gap-2 rounded-xl bg-white px-7 py-3.5 font-semibold text-brand-800 shadow-lg transition hover:-translate-y-0.5 hover:bg-brand-50">
              Crear la cuenta de mi clínica
              <ArrowIcon className="size-5" />
            </Link>
          </div>
        </section>
      </main>

      {/* ---------- Footer ---------- */}
      <footer className="border-t border-gray-100 bg-white">
        <div className="mx-auto grid max-w-7xl gap-10 px-4 py-14 sm:px-6 md:grid-cols-[1.5fr_1fr_1fr_1fr]">
          <div>
            <Logo />
            <p className="mt-4 max-w-xs text-sm leading-relaxed text-gray-500">Software para clínicas veterinarias en Colombia. Hecho con cariño por las mascotas y quienes las cuidan.</p>
          </div>
          <FooterCol
            title="Producto"
            links={[
              ["Funciones", "#funciones"],
              ["Precios", "#precios"],
              ["Preguntas frecuentes", "#preguntas"],
            ]}
          />
          <FooterCol
            title="Cuenta"
            links={[
              ["Crear cuenta", "/registro"],
              ["Ingresar", "/login"],
              ["Portal del tutor", "/portal/ingresar"],
            ]}
          />
          <FooterCol
            title="Legal"
            links={[
              ["Términos de uso", "/terminos"],
              ["Tratamiento de datos", "/privacidad"],
            ]}
          />
        </div>
        <div className="border-t border-gray-100 py-6 text-center text-xs text-gray-400">
          <LogoMark className="mx-auto mb-2 size-5 opacity-60" />© {new Date().getFullYear()} Zooetia · Colombia
        </div>
      </footer>
    </div>
  );
}

function Eyebrow({ children, dark = false }: { children: React.ReactNode; dark?: boolean }) {
  return <p className={cn("mb-3 text-sm font-semibold tracking-wide uppercase", dark ? "text-brand-300" : "text-brand-500")}>{children}</p>;
}

function SectionTitle({ eyebrow, title, text }: { eyebrow: string; title: string; text?: string }) {
  return (
    <div className="mx-auto max-w-2xl text-center">
      <Eyebrow>{eyebrow}</Eyebrow>
      <h2 className="text-3xl font-bold tracking-tight text-gray-900 sm:text-4xl">{title}</h2>
      {text && <p className="mt-4 text-lg leading-relaxed text-gray-600">{text}</p>}
    </div>
  );
}

function Bullets({ items, dark = false }: { items: string[]; dark?: boolean }) {
  return (
    <ul className="mt-8 space-y-3">
      {items.map((item) => (
        <li key={item} className={cn("flex items-start gap-3", dark ? "text-white/85" : "text-gray-700")}>
          <CheckIcon className="mt-0.5 size-5 shrink-0" />
          {item}
        </li>
      ))}
    </ul>
  );
}

function PlanLine({ label, featured, included = true, soon = false }: { label: string; featured: boolean; included?: boolean; soon?: boolean }) {
  return (
    <li className={cn("flex items-start gap-2.5", !included && (featured ? "text-white/35" : "text-gray-400"))}>
      {included ? (
        <svg viewBox="0 0 20 20" className={cn("mt-0.5 size-4 shrink-0", featured ? "text-brand-300" : "text-brand-500")} fill="none" stroke="currentColor" strokeWidth={2.6} strokeLinecap="round" strokeLinejoin="round" aria-hidden>
          <path d="m4.5 10.5 3.5 3.5 7.5-8" />
        </svg>
      ) : (
        <span aria-hidden className="mt-0.5 w-4 shrink-0 text-center">–</span>
      )}
      <span>
        <span className="sr-only">{included ? "Incluye: " : "No incluye: "}</span>
        {label}
        {soon && included && <span className={cn("ml-1.5 rounded px-1.5 py-0.5 text-[11px]", featured ? "bg-white/10 text-white/70" : "bg-gray-100 text-gray-500")}>pronto</span>}
      </span>
    </li>
  );
}

function FooterCol({ title, links }: { title: string; links: [string, string][] }) {
  return (
    <div>
      <p className="mb-4 text-sm font-semibold text-gray-900">{title}</p>
      <ul className="space-y-2.5 text-sm text-gray-500">
        {links.map(([label, href]) => (
          <li key={href}>
            {href.startsWith("#") ? (
              <a href={href} className="hover:text-brand-600">
                {label}
              </a>
            ) : (
              <Link href={href} className="hover:text-brand-600">
                {label}
              </Link>
            )}
          </li>
        ))}
      </ul>
    </div>
  );
}
