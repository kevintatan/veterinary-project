"use client";

import { LogoMark } from "@/components/brand/Logo";
import { formatCop } from "@/lib/settings";
import { cn } from "@/utils";
import Link from "next/link";
import { useEffect, useMemo, useState } from "react";

export type BookingInfo = {
  clinic: { name: string; slug: string; has_logo: boolean; phone: string | null; address: string | null; timezone: string };
  message: string | null;
  max_days_ahead: number;
  choose_professional: boolean;
  branches: { id: string; name: string; address: string | null; professionals: { id: string; name: string }[] }[];
  services: { id: string; name: string; category: string; duration_minutes: number; price: number | null }[];
  document_types: { value: string; label: string }[];
  species: { id: string; name: string }[];
};

/** From the owner portal (docs/reservas.md, B3): the tutor's data and pets, already known. */
export type BookingPrefill = {
  name: string;
  phone: string | null;
  document_type: string | null;
  document_number: string | null;
  pets: { id: string; name: string; species_id: string }[];
  petId?: string | null;
};

type Errors = Record<string, string[]>;

/** "+573186665544" → "318 666 5544" (what people write). */
const localPhone = (e164: string | null) => (e164 ?? "").replace(/^\+57/, "").replace(/^(\d{3})(\d{3})(\d{4})$/, "$1 $2 $3");

/** "YYYY-MM-DD" of a date in the clinic's timezone. */
const ymd = (d: Date, timeZone: string) => new Intl.DateTimeFormat("en-CA", { timeZone, year: "numeric", month: "2-digit", day: "2-digit" }).format(d);
const DAYS_SHOWN = 14;

/**
 * Public booking: service → day and time → your details → request sent.
 * The clinic always accepts or rejects the request (docs/reservas.md).
 */
export default function BookingFlow({ info, prefill, backHref }: { info: BookingInfo; prefill?: BookingPrefill; backHref?: string }) {
  const tz = info.clinic.timezone;
  const base = `/api/public/booking/${info.clinic.slug}`;

  const [branchId, setBranchId] = useState(info.branches[0]?.id ?? "");
  const [serviceId, setServiceId] = useState<string | null>(null);
  const [userId, setUserId] = useState("");
  const [date, setDate] = useState(() => ymd(new Date(), tz));
  const [slots, setSlots] = useState<string[] | null>(null);
  const [time, setTime] = useState<string | null>(null);
  const startPet = prefill?.pets.find((p) => p.id === prefill.petId) ?? (prefill?.pets.length === 1 ? prefill.pets[0] : undefined);
  const [petChoice, setPetChoice] = useState<string>(startPet?.id ?? (prefill?.pets.length ? "" : "other"));
  const [form, setForm] = useState({
    name: prefill?.name ?? "",
    phone: localPhone(prefill?.phone ?? null),
    document_type: prefill?.document_type ?? info.document_types[0]?.value ?? "CC",
    document_number: prefill?.document_number ?? "",
    pet_name: startPet?.name ?? "",
    species_id: startPet?.species_id ?? info.species[0]?.id ?? "",
    notes: "",
    accept_data: false,
    accept_whatsapp: false,
  });
  const choosePet = (id: string) => {
    setPetChoice(id);
    const pet = prefill?.pets.find((p) => p.id === id);
    setForm((f) => ({ ...f, pet_name: pet?.name ?? "", species_id: pet?.species_id ?? info.species[0]?.id ?? "" }));
  };
  const [errors, setErrors] = useState<Errors>({});
  const [message, setMessage] = useState<string | null>(null);
  const [sending, setSending] = useState(false);
  const [sent, setSent] = useState(false);
  const [slotsVersion, setSlotsVersion] = useState(0);
  const [now] = useState(() => Date.now()); // the day list is fixed when the page opens

  const branch = info.branches.find((b) => b.id === branchId);
  const service = info.services.find((s) => s.id === serviceId);

  const days = useMemo(() => {
    const out: { value: string; weekday: string; day: string; month: string }[] = [];
    for (let i = 0; i < Math.min(DAYS_SHOWN, info.max_days_ahead + 1); i++) {
      const d = new Date(now + i * 86_400_000);
      out.push({
        value: ymd(d, tz),
        weekday: i === 0 ? "Hoy" : i === 1 ? "Mañana" : d.toLocaleDateString("es-CO", { weekday: "short", timeZone: tz }).replace(".", ""),
        day: d.toLocaleDateString("es-CO", { day: "numeric", timeZone: tz }),
        month: d.toLocaleDateString("es-CO", { month: "short", timeZone: tz }).replace(".", ""),
      });
    }
    return out;
  }, [now, tz, info.max_days_ahead]);
  const maxDate = ymd(new Date(now + info.max_days_ahead * 86_400_000), tz);

  // Free times whenever service, day, branch or professional change.
  useEffect(() => {
    if (!serviceId || !branchId) return;
    const controller = new AbortController();
    const query = new URLSearchParams({ branch_id: branchId, service_id: serviceId, date, ...(userId ? { user_id: userId } : {}) });
    fetch(`${base}/slots?${query}`, { signal: controller.signal })
      .then((r) => (r.ok ? r.json() : { data: [] }))
      .then((r) => setSlots(r.data ?? []))
      .catch(() => {});
    return () => controller.abort();
  }, [base, branchId, serviceId, date, userId, slotsVersion]);

  const fmtTime = (iso: string) => new Date(iso).toLocaleTimeString("es-CO", { hour: "numeric", minute: "2-digit", timeZone: tz });
  const fmtDay = (iso: string) => new Date(iso).toLocaleDateString("es-CO", { weekday: "long", day: "numeric", month: "long", timeZone: tz });
  const hourOf = (iso: string) => Number(new Intl.DateTimeFormat("en-US", { hour: "numeric", hourCycle: "h23", timeZone: tz }).format(new Date(iso)));
  const groups = slots
    ? [
        { label: "Mañana", items: slots.filter((s) => hourOf(s) < 12) },
        { label: "Tarde", items: slots.filter((s) => hourOf(s) >= 12) },
      ].filter((g) => g.items.length)
    : [];

  const err = (f: string) => errors[f]?.[0];
  const choose = (patch: Partial<{ serviceId: string; date: string; userId: string; branchId: string }>) => {
    if (patch.serviceId !== undefined) setServiceId(patch.serviceId);
    if (patch.date !== undefined) setDate(patch.date);
    if (patch.userId !== undefined) setUserId(patch.userId);
    if (patch.branchId !== undefined) setBranchId(patch.branchId);
    setSlots(null);
    setTime(null);
  };

  async function submit(e: React.FormEvent) {
    e.preventDefault();
    if (!time || !service) return;
    setSending(true);
    setErrors({});
    setMessage(null);
    const response = await fetch(base, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ ...form, branch_id: branchId, service_id: service.id, starts_at: time, user_id: userId || null }),
    }).catch(() => null);
    setSending(false);
    const data = await response?.json().catch(() => ({}));

    if (response?.status === 201) {
      setSent(true);
      window.scrollTo({ top: 0, behavior: "smooth" });
      return;
    }
    if (response?.status === 429) return setMessage("Has enviado muchas solicitudes seguidas. Espera un momento e inténtalo de nuevo.");
    if (response?.status === 422 && data?.errors) {
      setErrors(data.errors);
      if (data.errors.starts_at) {
        // Someone took the time meanwhile: refresh the times.
        setTime(null);
        setSlots(null);
        setSlotsVersion((v) => v + 1);
      }
      return;
    }
    setMessage(data?.message ?? "No fue posible enviar la solicitud. Revisa tu conexión e inténtalo de nuevo.");
  }

  return (
    <div className="min-h-screen bg-gray-50 pb-16 text-gray-800">
      <header className="bg-brand-950 px-4 pt-8 pb-16 text-white">
        <div className="mx-auto flex max-w-2xl items-center gap-4">
          {info.clinic.has_logo ? (
            // eslint-disable-next-line @next/next/no-img-element -- served by our API route
            <img src={`${base}/logo`} alt="" className="size-16 shrink-0 rounded-2xl bg-white object-contain p-1.5" />
          ) : (
            <LogoMark className="size-14" />
          )}
          <div className="min-w-0">
            {backHref ? (
              <Link href={backHref} className="text-sm text-brand-200 hover:text-white">
                ← Mi portal
              </Link>
            ) : (
              <p className="text-sm text-brand-200">Pide tu cita en línea</p>
            )}
            <h1 className="truncate text-2xl font-bold">{info.clinic.name}</h1>
            {(info.clinic.address || info.clinic.phone) && <p className="mt-0.5 truncate text-sm text-white/70">{[info.clinic.address, info.clinic.phone].filter(Boolean).join(" · ")}</p>}
          </div>
        </div>
      </header>

      <main className="mx-auto -mt-10 max-w-2xl space-y-4 px-4">
        {sent && service && time ? (
          <section className="rounded-2xl bg-white p-6 text-center shadow-theme-md">
            <span className="mx-auto flex size-16 items-center justify-center rounded-full bg-brand-50 text-3xl">📨</span>
            <h2 className="mt-4 text-xl font-bold text-gray-900">¡Solicitud enviada!</h2>
            <p className="mt-2 text-gray-600">
              {info.clinic.name} revisará tu solicitud y te avisará por <b>WhatsApp</b> al {form.phone}. <b>Tu cita aún no está confirmada.</b>
            </p>
            <div className="mt-5 rounded-xl bg-gray-50 p-4 text-left text-sm">
              <p className="font-semibold text-gray-900">
                {service.name} para {form.pet_name}
              </p>
              <p className="text-gray-600 first-letter:uppercase">
                {fmtDay(time)}, {fmtTime(time)}
              </p>
              {branch && info.branches.length > 1 && <p className="text-gray-600">{branch.name}</p>}
            </div>
            {backHref && (
              <Link href={backHref} className="mt-5 inline-flex rounded-xl bg-brand-500 px-5 py-2.5 text-sm font-semibold text-white hover:bg-brand-600">
                Volver a mi portal
              </Link>
            )}
          </section>
        ) : (
          <>
            {info.message && <p className="rounded-2xl border border-warning-200 bg-warning-50 px-4 py-3 text-sm text-warning-800">{info.message}</p>}

            {/* 1. Service */}
            <Step n={1} title="¿Qué necesitas?">
              {info.branches.length > 1 && (
                <label className="mb-4 block">
                  <span className="mb-1.5 block text-sm font-medium text-gray-700">Sede</span>
                  <select value={branchId} onChange={(e) => choose({ branchId: e.target.value, userId: "" })} className="h-11 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm">
                    {info.branches.map((b) => (
                      <option key={b.id} value={b.id}>
                        {b.name}
                        {b.address ? ` · ${b.address}` : ""}
                      </option>
                    ))}
                  </select>
                </label>
              )}
              {info.services.length === 0 ? (
                <p className="text-sm text-gray-500">Esta clínica aún no ha publicado servicios para pedir en línea.</p>
              ) : (
                <div className="grid gap-2 sm:grid-cols-2">
                  {info.services.map((s) => (
                    <button
                      key={s.id}
                      type="button"
                      onClick={() => choose({ serviceId: s.id })}
                      aria-pressed={serviceId === s.id}
                      className={cn(
                        "rounded-xl border-2 px-4 py-3 text-left transition",
                        serviceId === s.id ? "border-brand-500 bg-brand-25" : "border-gray-200 bg-white hover:border-brand-200",
                      )}
                    >
                      <span className="block font-semibold text-gray-900">{s.name}</span>
                      <span className="text-sm text-gray-500">
                        {s.duration_minutes} min{s.price ? ` · ${formatCop(s.price)}` : ""}
                      </span>
                    </button>
                  ))}
                </div>
              )}
            </Step>

            {/* 2. Day and time */}
            {service && (
              <Step n={2} title="¿Cuándo?">
                {info.choose_professional && (branch?.professionals.length ?? 0) > 1 && (
                  <label className="mb-4 block">
                    <span className="mb-1.5 block text-sm font-medium text-gray-700">Profesional</span>
                    <select value={userId} onChange={(e) => choose({ userId: e.target.value })} className="h-11 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm">
                      <option value="">Cualquiera disponible</option>
                      {branch?.professionals.map((p) => (
                        <option key={p.id} value={p.id}>
                          {p.name}
                        </option>
                      ))}
                    </select>
                  </label>
                )}
                <div className="-mx-1 flex gap-2 overflow-x-auto px-1 pb-2" role="listbox" aria-label="Día">
                  {days.map((d) => (
                    <button
                      key={d.value}
                      type="button"
                      role="option"
                      aria-selected={date === d.value}
                      onClick={() => choose({ date: d.value })}
                      className={cn(
                        "flex w-16 shrink-0 flex-col items-center rounded-xl border-2 py-2 transition",
                        date === d.value ? "border-brand-500 bg-brand-500 text-white" : "border-gray-200 bg-white text-gray-700 hover:border-brand-200",
                      )}
                    >
                      <span className="text-[11px] capitalize opacity-80">{d.weekday}</span>
                      <span className="text-lg font-bold">{d.day}</span>
                      <span className="text-[11px] capitalize opacity-80">{d.month}</span>
                    </button>
                  ))}
                </div>
                {info.max_days_ahead >= DAYS_SHOWN && (
                  <label className="mt-1 flex items-center gap-2 text-sm text-gray-600">
                    Otra fecha:
                    <input type="date" min={days[0]?.value} max={maxDate} value={date} onChange={(e) => e.target.value && choose({ date: e.target.value })} className="rounded-lg border border-gray-300 px-2 py-1" />
                  </label>
                )}

                <div className="mt-4">
                  {slots === null ? (
                    <div className="grid grid-cols-4 gap-2">
                      {[1, 2, 3, 4].map((i) => (
                        <div key={i} className="h-10 animate-pulse rounded-lg bg-gray-100" />
                      ))}
                    </div>
                  ) : groups.length === 0 ? (
                    <p className="rounded-xl bg-gray-50 px-4 py-6 text-center text-sm text-gray-500">No hay horas disponibles este día. Prueba con otra fecha.</p>
                  ) : (
                    groups.map((g) => (
                      <div key={g.label} className="mb-3">
                        <p className="mb-2 text-xs font-semibold tracking-wide text-gray-400 uppercase">{g.label}</p>
                        <div className="grid grid-cols-3 gap-2 sm:grid-cols-5">
                          {g.items.map((s) => (
                            <button
                              key={s}
                              type="button"
                              onClick={() => setTime(s)}
                              aria-pressed={time === s}
                              className={cn(
                                "rounded-lg border-2 py-2 text-sm font-semibold tabular-nums transition",
                                time === s ? "border-brand-500 bg-brand-500 text-white" : "border-gray-200 bg-white text-gray-800 hover:border-brand-300",
                              )}
                            >
                              {fmtTime(s)}
                            </button>
                          ))}
                        </div>
                      </div>
                    ))
                  )}
                  {err("starts_at") && <p className="mt-2 text-sm text-error-600">{err("starts_at")}</p>}
                </div>
              </Step>
            )}

            {/* 3. Details */}
            {service && time && (
              <Step n={3} title="Tus datos">
                <form onSubmit={submit} noValidate className="space-y-4">
                  <div className="grid gap-4 sm:grid-cols-2">
                    <TextField label="Tu nombre" value={form.name} onChange={(v) => setForm({ ...form, name: v })} error={err("name")} autoComplete="name" />
                    <TextField label="Celular (WhatsApp)" value={form.phone} onChange={(v) => setForm({ ...form, phone: v })} error={err("phone")} type="tel" autoComplete="tel" placeholder="300 123 4567" />
                    <label className="block">
                      <span className="mb-1.5 block text-sm font-medium text-gray-700">Tipo de documento</span>
                      <select value={form.document_type} onChange={(e) => setForm({ ...form, document_type: e.target.value })} className="h-11 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm">
                        {info.document_types.map((t) => (
                          <option key={t.value} value={t.value}>
                            {t.label}
                          </option>
                        ))}
                      </select>
                    </label>
                    <TextField label="Número de documento" value={form.document_number} onChange={(v) => setForm({ ...form, document_number: v })} error={err("document_number") ?? err("document_type")} inputMode="numeric" autoComplete="off" />
                    {prefill && prefill.pets.length > 0 && (
                      <div className="sm:col-span-2">
                        <span className="mb-1.5 block text-sm font-medium text-gray-700">¿Para cuál mascota?</span>
                        <div className="flex flex-wrap gap-2">
                          {[...prefill.pets, { id: "other", name: "Otra mascota", species_id: "" }].map((p) => (
                            <button
                              key={p.id}
                              type="button"
                              onClick={() => choosePet(p.id)}
                              aria-pressed={petChoice === p.id}
                              className={cn(
                                "rounded-full border-2 px-4 py-1.5 text-sm font-medium transition",
                                petChoice === p.id ? "border-brand-500 bg-brand-500 text-white" : "border-gray-200 bg-white text-gray-700 hover:border-brand-300",
                              )}
                            >
                              {p.name}
                            </button>
                          ))}
                        </div>
                      </div>
                    )}
                    {petChoice === "other" && (
                    <TextField label="Nombre de tu mascota" value={form.pet_name} onChange={(v) => setForm({ ...form, pet_name: v })} error={err("pet_name")} />
                    )}
                    {petChoice === "other" && (
                    <label className="block">
                      <span className="mb-1.5 block text-sm font-medium text-gray-700">Especie</span>
                      <select value={form.species_id} onChange={(e) => setForm({ ...form, species_id: e.target.value })} className="h-11 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm">
                        {info.species.map((s) => (
                          <option key={s.id} value={s.id}>
                            {s.name}
                          </option>
                        ))}
                      </select>
                    </label>
                    )}
                  </div>
                  <label className="block">
                    <span className="mb-1.5 block text-sm font-medium text-gray-700">
                      ¿Algo que debamos saber? <span className="font-normal text-gray-400">(opcional)</span>
                    </span>
                    <textarea value={form.notes} onChange={(e) => setForm({ ...form, notes: e.target.value })} rows={2} maxLength={500} className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" />
                  </label>

                  <div className="space-y-2.5 rounded-xl bg-gray-50 p-4 text-sm text-gray-700">
                    <Consent checked={form.accept_data} onChange={(v) => setForm({ ...form, accept_data: v })} error={err("accept_data")}>
                      Autorizo a {info.clinic.name} el tratamiento de mis datos personales para gestionar mi cita, según la Ley 1581 de 2012.
                    </Consent>
                    <Consent checked={form.accept_whatsapp} onChange={(v) => setForm({ ...form, accept_whatsapp: v })} error={err("accept_whatsapp")}>
                      Autorizo que {info.clinic.name} me escriba por WhatsApp sobre esta cita.
                    </Consent>
                  </div>

                  <div className="rounded-xl border border-brand-200 bg-brand-25 p-4 text-sm">
                    <p className="font-semibold text-gray-900">
                      {service.name}
                      {form.pet_name ? ` para ${form.pet_name}` : ""}
                    </p>
                    <p className="text-gray-700 first-letter:uppercase">
                      {fmtDay(time)}, {fmtTime(time)}
                    </p>
                    <p className="mt-1 text-gray-500">La clínica revisará tu solicitud y te confirmará por WhatsApp.</p>
                  </div>

                  {message && <p className="rounded-lg bg-error-50 px-4 py-3 text-sm text-error-700">{message}</p>}

                  <button
                    type="submit"
                    disabled={sending || !form.accept_data || !form.accept_whatsapp || !petChoice}
                    className="w-full rounded-xl bg-brand-500 py-3.5 text-base font-semibold text-white transition hover:bg-brand-600 disabled:cursor-not-allowed disabled:opacity-50"
                  >
                    {sending ? "Enviando…" : "Enviar solicitud"}
                  </button>
                </form>
              </Step>
            )}
          </>
        )}
        <p className="pt-4 text-center text-xs text-gray-400">
          Reservas con{" "}
          <Link href="/" className="font-medium text-gray-500 hover:underline">
            Zooetia
          </Link>
        </p>
      </main>
    </div>
  );
}

function Step({ n, title, children }: { n: number; title: string; children: React.ReactNode }) {
  return (
    <section className="rounded-2xl bg-white p-5 shadow-theme-sm sm:p-6">
      <h2 className="mb-4 flex items-center gap-3 text-lg font-bold text-gray-900">
        <span className="flex size-7 items-center justify-center rounded-full bg-brand-500 text-sm text-white">{n}</span>
        {title}
      </h2>
      {children}
    </section>
  );
}

function TextField({ label, value, onChange, error, ...rest }: { label: string; value: string; onChange: (v: string) => void; error?: string } & Omit<React.InputHTMLAttributes<HTMLInputElement>, "onChange" | "value">) {
  return (
    <label className="block">
      <span className="mb-1.5 block text-sm font-medium text-gray-700">{label}</span>
      <input
        {...rest}
        value={value}
        onChange={(e) => onChange(e.target.value)}
        aria-invalid={!!error}
        className={cn("h-11 w-full rounded-lg border bg-white px-3 text-sm", error ? "border-error-400" : "border-gray-300")}
      />
      {error && <span className="mt-1 block text-xs text-error-600">{error}</span>}
    </label>
  );
}

function Consent({ checked, onChange, error, children }: { checked: boolean; onChange: (v: boolean) => void; error?: string; children: React.ReactNode }) {
  return (
    <label className="flex cursor-pointer items-start gap-2.5">
      <input type="checkbox" checked={checked} onChange={(e) => onChange(e.target.checked)} className="mt-0.5 size-4 shrink-0 accent-brand-500" />
      <span>
        {children}
        {error && <span className="mt-0.5 block text-xs text-error-600">{error}</span>}
      </span>
    </label>
  );
}
