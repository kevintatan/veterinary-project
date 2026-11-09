"use client";

import { Card, CardHeader, Skeleton } from "@/components/common/Card";
import { Field, Input, Select } from "@/components/form/fields";
import Button from "@/components/ui/button/Button";
import { api, ApiError } from "@/lib/api";
import type { ClinicProfile } from "@/lib/settings";
import { cn } from "@/utils";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useRouter } from "next/navigation";
import { useRef, useState } from "react";

export default function ClinicSection() {
  const clinic = useQuery({ queryKey: ["clinic"], queryFn: () => api<{ data: ClinicProfile }>("clinic").then((r) => r.data) });

  if (!clinic.data) return <Skeleton className="h-96" />;

  return (
    <div className="space-y-6">
      <LogoCard clinic={clinic.data} />
      <IdentityForm key={clinic.data.id} clinic={clinic.data} />
      <RulesCard clinic={clinic.data} />
    </div>
  );
}

function useSaveClinic() {
  const queryClient = useQueryClient();
  const router = useRouter();
  return useMutation({
    mutationFn: (body: Record<string, unknown>) => api<{ data: ClinicProfile }>("clinic", { method: "PATCH", body }),
    onSuccess: ({ data }) => {
      queryClient.setQueryData(["clinic"], data);
      router.refresh(); // header/sidebar read the clinic name from the server session
    },
  });
}

function LogoCard({ clinic }: { clinic: ClinicProfile }) {
  const queryClient = useQueryClient();
  const router = useRouter();
  const input = useRef<HTMLInputElement>(null);
  const done = ({ data }: { data: ClinicProfile }) => {
    queryClient.setQueryData(["clinic"], data);
    router.refresh();
  };

  const upload = useMutation({
    mutationFn: (file: File) => {
      const body = new FormData();
      body.append("logo", file);
      return api<{ data: ClinicProfile }>("clinic/logo", { method: "POST", body });
    },
    onSuccess: done,
  });
  const remove = useMutation({ mutationFn: () => api<{ data: ClinicProfile }>("clinic/logo", { method: "DELETE" }), onSuccess: done });
  const error = upload.error instanceof ApiError ? (upload.error.field("logo") ?? upload.error.message) : null;

  return (
    <Card>
      <CardHeader title="Logo" description="Aparece en el membrete de las historias clínicas y las fórmulas." />
      <div className="flex flex-wrap items-center gap-5 p-5">
        <div className="flex h-24 w-48 items-center justify-center rounded-xl border border-dashed border-gray-300 bg-gray-50 p-3 dark:border-gray-700 dark:bg-white/3">
          {clinic.logo_url ? (
            // eslint-disable-next-line @next/next/no-img-element -- private image behind the BFF
            <img src={`/api/backend/${clinic.logo_url}`} alt={`Logo de ${clinic.name}`} className="max-h-full max-w-full object-contain" />
          ) : (
            <span className="text-theme-xs text-gray-400">Sin logo</span>
          )}
        </div>
        <div className="space-y-2">
          <div className="flex gap-2">
            <Button size="sm" variant="outline" loading={upload.isPending} onClick={() => input.current?.click()}>
              {clinic.logo_url ? "Cambiar logo" : "Subir logo"}
            </Button>
            {clinic.logo_url && (
              <Button size="sm" variant="ghost" loading={remove.isPending} onClick={() => remove.mutate()}>
                Quitar
              </Button>
            )}
          </div>
          <p className="text-theme-xs text-gray-500">PNG con fondo transparente se ve mejor. Máx. 4 MB.</p>
          {error && <p className="text-theme-xs text-error-500">{error}</p>}
        </div>
        <input
          ref={input}
          type="file"
          accept="image/png,image/jpeg,image/webp"
          className="hidden"
          onChange={(e) => {
            const file = e.target.files?.[0];
            if (file) upload.mutate(file);
            e.target.value = "";
          }}
        />
      </div>
    </Card>
  );
}

function IdentityForm({ clinic }: { clinic: ClinicProfile }) {
  const save = useSaveClinic();
  const [form, setForm] = useState({
    name: clinic.name,
    legal_name: clinic.legal_name ?? "",
    tax_id: clinic.tax_id ?? "",
    email: clinic.email ?? "",
    phone: clinic.phone ?? "",
    address: clinic.address ?? "",
    website: clinic.website ?? "",
  });
  const set = (key: keyof typeof form) => (e: React.ChangeEvent<HTMLInputElement>) => setForm((f) => ({ ...f, [key]: e.target.value }));
  const error = save.error instanceof ApiError ? save.error : null;
  const fe = (k: string) => error?.field(k);
  const dirty = Object.entries(form).some(([k, v]) => v !== ((clinic as Record<string, unknown>)[k] ?? ""));

  return (
    <Card>
      <CardHeader title="Datos de la clínica" description="Se usan en el membrete de los documentos y, más adelante, en la facturación." />
      <form
        className="grid gap-4 p-5 sm:grid-cols-2"
        onSubmit={(e) => {
          e.preventDefault();
          save.mutate(Object.fromEntries(Object.entries(form).map(([k, v]) => [k, k === "name" ? v.trim() : v.trim() || null])));
        }}
      >
        <Field label="Nombre comercial" htmlFor="cl-name" error={fe("name")}>
          <Input id="cl-name" value={form.name} onChange={set("name")} required maxLength={150} />
        </Field>
        <Field label="Razón social" htmlFor="cl-legal" optional error={fe("legal_name")}>
          <Input id="cl-legal" value={form.legal_name} onChange={set("legal_name")} placeholder="Anipet Veterinaria S.A.S." maxLength={200} />
        </Field>
        <Field label="NIT" htmlFor="cl-nit" optional hint="Con dígito de verificación: 901234567-8" error={fe("tax_id")}>
          <Input id="cl-nit" value={form.tax_id} onChange={set("tax_id")} inputMode="numeric" maxLength={20} />
        </Field>
        <Field label="Teléfono" htmlFor="cl-phone" optional error={fe("phone")}>
          <Input id="cl-phone" value={form.phone} onChange={set("phone")} maxLength={20} />
        </Field>
        <Field label="Correo" htmlFor="cl-email" optional error={fe("email")}>
          <Input id="cl-email" type="email" value={form.email} onChange={set("email")} />
        </Field>
        <Field label="Sitio web" htmlFor="cl-web" optional error={fe("website")}>
          <Input id="cl-web" type="url" value={form.website} onChange={set("website")} placeholder="https://" />
        </Field>
        <Field label="Dirección principal" htmlFor="cl-address" optional className="sm:col-span-2" hint="Cada sede tiene su dirección propia en “Sedes”.">
          <Input id="cl-address" value={form.address} onChange={set("address")} />
        </Field>
        <div className="flex items-center justify-end gap-3 sm:col-span-2">
          {save.isSuccess && !dirty && <span className="text-theme-xs text-success-600">Guardado</span>}
          <Button type="submit" size="sm" disabled={!dirty} loading={save.isPending}>
            Guardar cambios
          </Button>
        </div>
      </form>
    </Card>
  );
}

function RulesCard({ clinic }: { clinic: ClinicProfile }) {
  const save = useSaveClinic();
  const s = clinic.settings;
  const update = (patch: Partial<ClinicProfile["settings"]>) => save.mutate({ settings: patch });

  return (
    <Card>
      <CardHeader title="Reglas de la clínica" description="Se aplican a todo el equipo." />
      <ul className="divide-y divide-gray-100 dark:divide-gray-800">
        <RuleRow title="Ventana de edición de la historia clínica" description="Tiempo en que el autor puede corregir un registro. Después solo un administrador.">
          <Select aria-label="Ventana de edición" className="w-36" value={s.edit_window_hours} onChange={(e) => update({ edit_window_hours: Number(e.target.value) })}>
            {[12, 24, 48, 72].map((h) => (
              <option key={h} value={h}>
                {h} horas
              </option>
            ))}
          </Select>
        </RuleRow>
        <RuleRow title="Duración por defecto de una cita" description="Cuando la cita no tiene servicio.">
          <Select aria-label="Duración por defecto" className="w-36" value={s.default_appointment_minutes} onChange={(e) => update({ default_appointment_minutes: Number(e.target.value) })}>
            {[15, 20, 30, 45, 60].map((m) => (
              <option key={m} value={m}>
                {m} minutos
              </option>
            ))}
          </Select>
        </RuleRow>
        <RuleRow title="Evitar citas cruzadas" description="Un profesional no puede tener dos citas a la misma hora.">
          <Switch checked={s.prevent_overlaps} label="Evitar citas cruzadas" onChange={(v) => update({ prevent_overlaps: v })} />
        </RuleRow>
      </ul>
      {save.error && <p className="px-5 pb-4 text-theme-xs text-error-500">{save.error.message}</p>}
    </Card>
  );
}

function RuleRow({ title, description, children }: { title: string; description: string; children: React.ReactNode }) {
  return (
    <li className="flex flex-wrap items-center justify-between gap-4 px-5 py-4">
      <div className="min-w-0 flex-1">
        <p className="text-theme-sm font-medium text-gray-800 dark:text-white/90">{title}</p>
        <p className="text-theme-xs text-gray-500 dark:text-gray-400">{description}</p>
      </div>
      {children}
    </li>
  );
}

export function Switch({ checked, onChange, label, disabled }: { checked: boolean; onChange: (v: boolean) => void; label: string; disabled?: boolean }) {
  return (
    <button
      type="button"
      role="switch"
      aria-checked={checked}
      aria-label={label}
      disabled={disabled}
      onClick={() => onChange(!checked)}
      className={cn("relative h-6 w-11 shrink-0 rounded-full transition disabled:opacity-50", checked ? "bg-brand-500" : "bg-gray-200 dark:bg-gray-700")}
    >
      <span className={cn("absolute top-0.5 left-0.5 h-5 w-5 rounded-full bg-white shadow-theme-xs transition", checked && "translate-x-5")} />
    </button>
  );
}
