"use client";

import { Card, CardHeader, Skeleton } from "@/components/common/Card";
import Button from "@/components/ui/button/Button";
import { Modal } from "@/components/ui/modal";
import { api } from "@/lib/api";
import { formatCop } from "@/lib/settings";
import { cn } from "@/utils";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useRouter } from "next/navigation";
import { useState } from "react";

type Limit = { used: number; limit: number | null };
type PlanOption = {
  code: string;
  name: string;
  price_monthly: number;
  features: { key: string; label: string; soon: boolean; included: boolean }[];
  limits: { branches: number | null; whatsapp_messages: number; dian_documents: number };
};
type Billing = {
  subscription: {
    status: "trialing" | "active" | "past_due" | "expired" | "canceled";
    plan: { code: string; name: string };
    trial_ends_at: string | null;
    trial_days_left: number | null;
    current_period_end: string | null;
    grace_ends_at: string | null;
    allows_writes: boolean;
  } | null;
  usage: { whatsapp_messages: Limit; branches: Limit; users: Limit };
  plans: PlanOption[];
  request: { plan: string; plan_name: string; requested_at: string; requested_by: string } | null;
  online_payment: boolean;
};

const day = (iso: string) => new Date(iso).toLocaleDateString("es-CO", { day: "numeric", month: "long", year: "numeric", timeZone: "America/Bogota" });

/**
 * Configuración → Plan y facturación (docs/cobro.md). Online payment with
 * Mercado Pago comes later: today "Elegir" leaves a request for the team.
 */
export default function BillingSection() {
  const queryClient = useQueryClient();
  const router = useRouter();
  const [choosing, setChoosing] = useState<PlanOption | null>(null);

  const billing = useQuery({ queryKey: ["billing"], queryFn: () => api<{ data: Billing }>("billing").then((r) => r.data) });
  const request = useMutation({
    mutationFn: (plan: string) => api<{ data: Billing }>("billing/request", { method: "POST", body: { plan } }).then((r) => r.data),
    onSuccess: (d) => {
      queryClient.setQueryData(["billing"], d);
      setChoosing(null);
    },
  });

  const b = billing.data;
  if (!b) return <Skeleton className="h-96" />;
  const s = b.subscription;
  const isActivePlan = (code: string) => s?.plan.code === code && (s.status === "active" || s.status === "past_due");

  return (
    <div className="space-y-6">
      <StatusCard billing={b} />

      {b.request && (
        <div className="rounded-xl border border-brand-200 bg-brand-25 px-5 py-4 text-sm text-brand-800 dark:border-brand-500/30 dark:bg-brand-500/10 dark:text-brand-200">
          <b>Solicitud recibida: plan {b.request.plan_name}.</b> El equipo de Zooetia te contactará para activarlo y acordar el pago. Enviada por {b.request.requested_by} el{" "}
          {day(b.request.requested_at)}.
        </div>
      )}

      <Card>
        <CardHeader title="Uso de este mes" description="Se reinicia el primer día de cada mes." />
        <div className="grid gap-5 p-5 sm:grid-cols-3">
          <Usage label="Mensajes de WhatsApp" value={b.usage.whatsapp_messages} />
          <Usage label="Sedes activas" value={b.usage.branches} />
          <Usage label="Personas del equipo" value={b.usage.users} />
        </div>
      </Card>

      <div>
        <h3 className="mb-1 text-base font-semibold text-gray-800 dark:text-white/90">Planes</h3>
        <p className="mb-4 text-theme-sm text-gray-500">Precios mensuales en pesos colombianos. Todos incluyen agenda, historias clínicas, tutores y mascotas, carné con QR, documentos firmados y portal del tutor.</p>
        <div className="grid gap-4 lg:grid-cols-3">
          {b.plans.map((p) => {
            const current = isActivePlan(p.code);
            const requested = b.request?.plan === p.code;
            return (
              <div key={p.code} className={cn("flex flex-col rounded-2xl border bg-white p-5 dark:bg-gray-900", current ? "border-brand-500 ring-2 ring-brand-500/20" : "border-gray-200 dark:border-gray-800")}>
                <div className="flex items-start justify-between gap-2">
                  <h4 className="text-lg font-semibold text-gray-800 dark:text-white/90">{p.name}</h4>
                  {current && <span className="rounded-full bg-brand-50 px-2.5 py-0.5 text-theme-xs font-medium text-brand-700">Tu plan</span>}
                </div>
                <p className="mt-2 text-2xl font-semibold text-gray-900 dark:text-white">
                  {formatCop(p.price_monthly)}
                  <span className="text-sm font-normal text-gray-500"> /mes</span>
                </p>
                <ul className="mt-4 flex-1 space-y-2 text-theme-sm">
                  <Feature included label={p.limits.branches === null ? "Sedes ilimitadas" : `${p.limits.branches} sede`} />
                  {p.limits.whatsapp_messages > 0 && <Feature included label={`${p.limits.whatsapp_messages} mensajes de WhatsApp al mes`} />}
                  {p.features.map((f) => (
                    <Feature key={f.key} included={f.included} label={f.label} soon={f.soon} />
                  ))}
                  {p.limits.dian_documents > 0 && <Feature included label={`${p.limits.dian_documents} documentos DIAN al mes`} soon />}
                </ul>
                <Button className="mt-5 w-full" variant={current || requested ? "outline" : "primary"} disabled={current || requested} onClick={() => setChoosing(p)}>
                  {current ? "Plan actual" : requested ? "Solicitado" : `Elegir ${p.name}`}
                </Button>
              </div>
            );
          })}
        </div>
      </div>

      {choosing && (
        <Modal isOpen onClose={() => setChoosing(null)} className="m-4 w-full max-w-md rounded-2xl bg-white p-6 dark:bg-gray-900">
          <h2 className="pr-12 text-lg font-semibold text-gray-800 dark:text-white/90">Plan {choosing.name}</h2>
          <p className="mt-2 pr-12 text-sm text-gray-600 dark:text-gray-300">{formatCop(choosing.price_monthly)} al mes.</p>
          <div className="mt-4 rounded-lg bg-gray-50 px-4 py-3 text-sm text-gray-700 dark:bg-white/5 dark:text-gray-300">
            El pago en línea con <b>Mercado Pago</b> llega muy pronto. Por ahora, registramos tu solicitud y el equipo de Zooetia te contacta para activar el plan y acordar el pago.
          </div>
          {request.isError && <p className="mt-3 text-sm text-error-600">No fue posible enviar la solicitud. Intenta de nuevo.</p>}
          <div className="mt-6 flex justify-end gap-3">
            <Button variant="outline" onClick={() => setChoosing(null)}>
              Cancelar
            </Button>
            <Button
              loading={request.isPending}
              onClick={() =>
                request.mutate(choosing.code, {
                  onSuccess: () => router.refresh(),
                })
              }
            >
              Solicitar plan {choosing.name}
            </Button>
          </div>
        </Modal>
      )}
    </div>
  );
}

function StatusCard({ billing }: { billing: Billing }) {
  const s = billing.subscription;
  const tone = !s || !s.allows_writes ? "error" : s.status === "past_due" ? "warning" : "brand";
  const tones = {
    error: "border-error-200 bg-error-50 text-error-800 dark:border-error-500/30 dark:bg-error-500/10 dark:text-error-200",
    warning: "border-warning-200 bg-warning-50 text-warning-800 dark:border-warning-500/30 dark:bg-warning-500/10 dark:text-warning-200",
    brand: "border-brand-200 bg-brand-25 text-brand-800 dark:border-brand-500/30 dark:bg-brand-500/10 dark:text-brand-200",
  };

  let title: string;
  let detail: string;
  if (!s || !s.allows_writes) {
    title = s?.status === "trialing" || s?.trial_ends_at ? "Tu prueba gratis terminó" : "Tu plan está vencido";
    detail = "Tu información sigue disponible para consulta, pero para registrar cambios debes activar un plan.";
  } else if (s.status === "trialing") {
    title = `Prueba gratis · ${s.trial_days_left} ${s.trial_days_left === 1 ? "día restante" : "días restantes"}`;
    detail = `Tienes todas las funciones del plan ${s.plan.name} hasta el ${day(s.trial_ends_at!)}. Elige un plan antes de esa fecha para seguir sin interrupciones.`;
  } else if (s.status === "past_due") {
    title = `Plan ${s.plan.name} · pago pendiente`;
    detail = `El período terminó el ${day(s.current_period_end!)}. Puedes seguir trabajando hasta el ${day(s.grace_ends_at!)}; después la cuenta queda en modo consulta.`;
  } else {
    title = `Plan ${s.plan.name} · activo`;
    detail = s.current_period_end ? `Pagado hasta el ${day(s.current_period_end)}.` : "Activo.";
  }

  return (
    <div className={cn("rounded-2xl border px-5 py-4", tones[tone])}>
      <p className="text-base font-semibold">{title}</p>
      <p className="mt-1 text-sm opacity-90">{detail}</p>
    </div>
  );
}

function Usage({ label, value }: { label: string; value: Limit }) {
  const percent = value.limit ? Math.min(100, Math.round((value.used / value.limit) * 100)) : null;
  return (
    <div>
      <p className="text-theme-sm text-gray-500">{label}</p>
      <p className="mt-1 text-xl font-semibold text-gray-800 dark:text-white/90">
        {value.used}
        <span className="text-sm font-normal text-gray-500">{value.limit === null ? " · sin límite" : value.limit === 0 ? " · no incluido" : ` de ${value.limit}`}</span>
      </p>
      {percent !== null && (
        <div className="mt-2 h-1.5 overflow-hidden rounded-full bg-gray-100 dark:bg-white/5" aria-hidden>
          <div className={cn("h-full rounded-full", percent >= 90 ? "bg-error-500" : percent >= 70 ? "bg-warning-500" : "bg-brand-500")} style={{ width: `${percent}%` }} />
        </div>
      )}
    </div>
  );
}

function Feature({ included, label, soon = false }: { included: boolean; label: string; soon?: boolean }) {
  return (
    <li className={cn("flex items-start gap-2", !included && "text-gray-400 dark:text-gray-500")}>
      <span aria-hidden className={cn("mt-0.5 w-4 shrink-0 text-center font-bold", included ? "text-brand-600" : "text-gray-300")}>
        {included ? "✓" : "–"}
      </span>
      <span>
        <span className="sr-only">{included ? "Incluye: " : "No incluye: "}</span>
        {label}
        {soon && included && <span className="ml-1.5 rounded bg-gray-100 px-1.5 py-0.5 text-[11px] text-gray-500 dark:bg-white/5">próximamente</span>}
      </span>
    </li>
  );
}
