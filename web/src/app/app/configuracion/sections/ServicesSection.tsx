"use client";

import { Card, CardHeader, Skeleton } from "@/components/common/Card";
import SidePanel from "@/components/common/SidePanel";
import { Checkbox, Field, Input, Select } from "@/components/form/fields";
import Button from "@/components/ui/button/Button";
import { api, ApiError } from "@/lib/api";
import { AGENDA_COLORS, formatCop, SERVICE_CATEGORIES, type ServiceItem } from "@/lib/settings";
import { cn } from "@/utils";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useState } from "react";
import { Switch } from "./ClinicSection";

export default function ServicesSection() {
  const [editing, setEditing] = useState<ServiceItem | "new" | null>(null);
  const services = useQuery({
    queryKey: ["agenda", "services", "all"],
    queryFn: () => api<{ data: ServiceItem[] }>("services?include_inactive=1").then((r) => r.data),
  });

  return (
    <Card>
      <CardHeader
        title="Servicios"
        description="La duración define cuánto ocupa la cita en la agenda."
        action={
          <Button size="sm" onClick={() => setEditing("new")}>
            Nuevo servicio
          </Button>
        }
      />
      {!services.data ? (
        <Skeleton className="m-5 h-40" />
      ) : (
        <div className="overflow-x-auto">
          <table className="w-full min-w-[560px] text-left text-theme-sm">
            <thead>
              <tr className="border-b border-gray-100 text-theme-xs text-gray-500 dark:border-gray-800">
                <th className="px-5 py-2.5 font-medium">Servicio</th>
                <th className="px-5 py-2.5 font-medium">Categoría</th>
                <th className="px-5 py-2.5 text-right font-medium">Duración</th>
                <th className="px-5 py-2.5 text-right font-medium">Precio</th>
                <th className="px-5 py-2.5" />
              </tr>
            </thead>
            <tbody>
              {services.data.map((s) => (
                <tr key={s.id} className={cn("border-b border-gray-100 last:border-0 dark:border-gray-800", !s.is_active && "opacity-50")}>
                  <td className="px-5 py-3">
                    <span className="flex items-center gap-2.5 font-medium text-gray-800 dark:text-white/90">
                      <span className="h-2.5 w-2.5 shrink-0 rounded-full" style={{ backgroundColor: s.color ?? "#98A2B3" }} aria-hidden />
                      {s.name}
                      {!s.is_active && <span className="text-theme-xs font-normal text-gray-500">(inactivo)</span>}
                    </span>
                  </td>
                  <td className="px-5 py-3 text-gray-600 dark:text-gray-400">{s.category.label}</td>
                  <td className="px-5 py-3 text-right text-gray-600 tabular dark:text-gray-400">{s.duration_minutes} min</td>
                  <td className="px-5 py-3 text-right text-gray-800 tabular dark:text-white/90">{s.price > 0 ? formatCop(s.price) : "—"}</td>
                  <td className="px-5 py-3 text-right">
                    <Button size="sm" variant="ghost" onClick={() => setEditing(s)}>
                      Editar
                    </Button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
      {editing && <ServicePanel service={editing === "new" ? undefined : editing} onClose={() => setEditing(null)} />}
    </Card>
  );
}

function ServicePanel({ service, onClose }: { service?: ServiceItem; onClose: () => void }) {
  const queryClient = useQueryClient();
  const [form, setForm] = useState({
    name: service?.name ?? "",
    category: service?.category.value ?? "consultation",
    duration_minutes: String(service?.duration_minutes ?? 30),
    price: service ? String(service.price) : "",
    color: service?.color ?? AGENDA_COLORS[0],
    is_bookable_online: service?.is_bookable_online ?? true,
    is_active: service?.is_active ?? true,
  });

  const save = useMutation({
    mutationFn: () => {
      const body = {
        ...form,
        name: form.name.trim(),
        duration_minutes: Number(form.duration_minutes),
        price: form.price ? Number(form.price.replace(/\D/g, "")) : 0,
      };
      return service ? api(`services/${service.id}`, { method: "PATCH", body }) : api("services", { method: "POST", body });
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["agenda"] });
      onClose();
    },
  });
  const error = save.error instanceof ApiError ? save.error : null;

  return (
    <SidePanel
      title={service ? `Editar ${service.name}` : "Nuevo servicio"}
      onClose={onClose}
      footer={
        <div className="flex justify-end gap-3">
          <Button size="sm" variant="outline" onClick={onClose}>
            Cancelar
          </Button>
          <Button size="sm" type="submit" form="service-form" disabled={form.name.trim().length < 2 || !form.duration_minutes} loading={save.isPending}>
            Guardar
          </Button>
        </div>
      }
    >
      <form
        id="service-form"
        className="space-y-4"
        onSubmit={(e) => {
          e.preventDefault();
          save.mutate();
        }}
      >
        <Field label="Nombre" htmlFor="sv-name" error={error?.field("name")}>
          <Input id="sv-name" autoFocus value={form.name} onChange={(e) => setForm((f) => ({ ...f, name: e.target.value }))} placeholder="Consulta general" />
        </Field>
        <Field label="Categoría" htmlFor="sv-cat">
          <Select id="sv-cat" value={form.category} onChange={(e) => setForm((f) => ({ ...f, category: e.target.value }))}>
            {SERVICE_CATEGORIES.map(([v, l]) => (
              <option key={v} value={v}>
                {l}
              </option>
            ))}
          </Select>
        </Field>
        <div className="grid grid-cols-2 gap-3">
          <Field label="Duración" htmlFor="sv-dur" error={error?.field("duration_minutes")}>
            <Select id="sv-dur" value={form.duration_minutes} onChange={(e) => setForm((f) => ({ ...f, duration_minutes: e.target.value }))}>
              {[...new Set([10, 15, 20, 30, 45, 60, 90, 120, 180, 240, Number(form.duration_minutes)])]
                .sort((a, b) => a - b)
                .map((m) => (
                  <option key={m} value={m}>
                    {m < 60 ? `${m} min` : `${m / 60} h`}
                  </option>
                ))}
            </Select>
          </Field>
          <Field label="Precio (COP)" htmlFor="sv-price" optional error={error?.field("price")}>
            <Input id="sv-price" inputMode="numeric" value={form.price} onChange={(e) => setForm((f) => ({ ...f, price: e.target.value }))} placeholder="60000" />
          </Field>
        </div>
        <div>
          <p className="mb-2 text-theme-sm font-medium text-gray-700 dark:text-gray-400">Color en la agenda</p>
          <div className="flex flex-wrap gap-2" role="radiogroup" aria-label="Color">
            {AGENDA_COLORS.map((c) => (
              <button
                key={c}
                type="button"
                role="radio"
                aria-checked={form.color === c}
                aria-label={c}
                onClick={() => setForm((f) => ({ ...f, color: c }))}
                className={cn("h-7 w-7 rounded-full ring-offset-2 dark:ring-offset-gray-900", form.color === c && "ring-2 ring-gray-900 dark:ring-white")}
                style={{ backgroundColor: c }}
              />
            ))}
          </div>
        </div>
        <Checkbox checked={form.is_bookable_online} onChange={(e) => setForm((f) => ({ ...f, is_bookable_online: e.target.checked }))} label="Los tutores pueden pedirlo en línea" description="Aparece en tu página de reserva en línea (Configuración → Reserva en línea)." />
        {service && (
          <div className="flex items-center justify-between rounded-lg border border-gray-200 px-4 py-3 dark:border-gray-800">
            <div>
              <p className="text-theme-sm font-medium text-gray-800 dark:text-white/90">Servicio activo</p>
              <p className="text-theme-xs text-gray-500">Inactivo: no se ofrece al agendar; las citas pasadas lo conservan.</p>
            </div>
            <Switch checked={form.is_active} label="Servicio activo" onChange={(v) => setForm((f) => ({ ...f, is_active: v }))} />
          </div>
        )}
      </form>
    </SidePanel>
  );
}
