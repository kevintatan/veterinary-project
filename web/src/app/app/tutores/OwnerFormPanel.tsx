"use client";

import SidePanel from "@/components/common/SidePanel";
import { Checkbox, Field, FormSection, Input, Select, Textarea } from "@/components/form/fields";
import Button from "@/components/ui/button/Button";
import { api, ApiError } from "@/lib/api";
import { DOCUMENT_TYPES, fromE164, type Owner, REFERRAL_SOURCES, toE164 } from "@/lib/patients";
import { useMutation, useQueryClient } from "@tanstack/react-query";
import { useState } from "react";

/**
 * Create or edit an owner. On creation the Habeas Data authorization (Ley 1581)
 * is mandatory and each contact channel is an explicit opt-in.
 */
export default function OwnerFormPanel({
  owner,
  initialName = "",
  onClose,
  onSaved,
}: {
  owner?: Owner;
  initialName?: string;
  onClose: () => void;
  onSaved?: (owner: Owner) => void;
}) {
  const queryClient = useQueryClient();
  const editing = !!owner;

  const [form, setForm] = useState({
    full_name: owner?.full_name ?? initialName,
    document_type: owner?.document_type?.value ?? "CC",
    document_number: owner?.document_number ?? "",
    mobile_phone: fromE164(owner?.mobile_phone ?? null),
    email: owner?.email ?? "",
    phone: fromE164(owner?.phone ?? null),
    address: owner?.address ?? "",
    neighborhood: owner?.neighborhood ?? "",
    alt_contact_name: owner?.alt_contact_name ?? "",
    alt_contact_phone: fromE164(owner?.alt_contact_phone ?? null),
    referral_source: owner?.referral_source ?? "",
    important_notes: owner?.important_notes ?? "",
  });
  const [consents, setConsents] = useState({ data_processing: false, whatsapp: true, sms: false, email: false });
  const set = (key: keyof typeof form) => (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement>) =>
    setForm((f) => ({ ...f, [key]: e.target.value }));

  const save = useMutation({
    mutationFn: () => {
      const body = {
        ...form,
        document_type: form.document_number.trim() ? form.document_type : null,
        document_number: form.document_number.replace(/[.\s-]/g, "") || null,
        mobile_phone: toE164(form.mobile_phone),
        phone: toE164(form.phone),
        alt_contact_phone: toE164(form.alt_contact_phone),
        email: form.email.trim() || null,
        ...(editing ? {} : { consents }),
      };
      return editing
        ? api<{ data: Owner }>(`owners/${owner.id}`, { method: "PATCH", body })
        : api<{ data: Owner }>("owners", { method: "POST", body });
    },
    onSuccess: ({ data }) => {
      queryClient.invalidateQueries({ queryKey: ["owners"] });
      // Close first: the parent's onClose may touch the URL, then onSaved navigates.
      onClose();
      onSaved?.(data);
    },
  });

  const error = save.error instanceof ApiError ? save.error : null;
  const fieldError = (name: string) => error?.field(name);
  const canSubmit = form.full_name.trim().length > 1 && (editing || consents.data_processing) && !save.isPending;

  return (
    <SidePanel
      title={editing ? "Editar tutor" : "Nuevo tutor"}
      description={editing ? owner.full_name : "Después podrás registrar sus mascotas."}
      onClose={onClose}
      footer={
        <div className="flex items-center justify-between gap-3">
          <p className="text-theme-xs text-error-500">{error && Object.keys(error.errors).length === 0 ? error.message : error ? "Revisa los campos marcados." : ""}</p>
          <div className="flex gap-3">
            <Button variant="outline" size="sm" onClick={onClose}>
              Cancelar
            </Button>
            <Button size="sm" type="submit" form="owner-form" disabled={!canSubmit} loading={save.isPending}>
              {editing ? "Guardar cambios" : "Registrar tutor"}
            </Button>
          </div>
        </div>
      }
    >
      <form
        id="owner-form"
        className="space-y-6"
        onSubmit={(e) => {
          e.preventDefault();
          if (canSubmit) save.mutate();
        }}
      >
        <FormSection title="Datos personales">
          <Field label="Nombre completo" htmlFor="o-name" error={fieldError("full_name")}>
            <Input id="o-name" value={form.full_name} onChange={set("full_name")} autoFocus={!editing} required maxLength={150} invalid={!!fieldError("full_name")} />
          </Field>
          <div className="grid grid-cols-[minmax(0,2fr)_minmax(0,3fr)] gap-3">
            <Field label="Tipo de documento" htmlFor="o-doctype">
              <Select id="o-doctype" value={form.document_type} onChange={set("document_type")}>
                {DOCUMENT_TYPES.map(([value, label]) => (
                  <option key={value} value={value}>
                    {label}
                  </option>
                ))}
              </Select>
            </Field>
            <Field label="Número" htmlFor="o-doc" optional error={fieldError("document_number") ?? fieldError("document_type")}>
              <Input id="o-doc" value={form.document_number} onChange={set("document_number")} inputMode="numeric" maxLength={20} invalid={!!fieldError("document_number")} />
            </Field>
          </div>
        </FormSection>

        <FormSection title="Contacto">
          <div className="grid gap-3 sm:grid-cols-2">
            <Field label="Celular" htmlFor="o-mobile" hint="Para recordatorios por WhatsApp." error={fieldError("mobile_phone")}>
              <Input id="o-mobile" type="tel" value={form.mobile_phone} onChange={set("mobile_phone")} placeholder="312 345 6789" invalid={!!fieldError("mobile_phone")} />
            </Field>
            <Field label="Correo" htmlFor="o-email" optional error={fieldError("email")}>
              <Input id="o-email" type="email" value={form.email} onChange={set("email")} invalid={!!fieldError("email")} />
            </Field>
          </div>
          <div className="grid gap-3 sm:grid-cols-[minmax(0,3fr)_minmax(0,2fr)]">
            <Field label="Dirección" htmlFor="o-address" optional>
              <Input id="o-address" value={form.address} onChange={set("address")} placeholder="Cra 27 # 45-12, apto 301" />
            </Field>
            <Field label="Barrio" htmlFor="o-hood" optional>
              <Input id="o-hood" value={form.neighborhood} onChange={set("neighborhood")} />
            </Field>
          </div>
          <div className="grid gap-3 sm:grid-cols-2">
            <Field label="Contacto alterno" htmlFor="o-alt" optional>
              <Input id="o-alt" value={form.alt_contact_name} onChange={set("alt_contact_name")} placeholder="Ej.: hija, vecino" />
            </Field>
            <Field label="Teléfono alterno" htmlFor="o-altphone" optional error={fieldError("alt_contact_phone")}>
              <Input id="o-altphone" type="tel" value={form.alt_contact_phone} onChange={set("alt_contact_phone")} />
            </Field>
          </div>
        </FormSection>

        <FormSection title="Otros">
          <Field label="¿Cómo nos conoció?" htmlFor="o-ref" optional>
            <Select id="o-ref" value={form.referral_source} onChange={set("referral_source")}>
              <option value="">Sin dato</option>
              {REFERRAL_SOURCES.map(([value, label]) => (
                <option key={value} value={value}>
                  {label}
                </option>
              ))}
            </Select>
          </Field>
          <Field label="Notas importantes" htmlFor="o-notes" optional hint="Visibles para todo el equipo.">
            <Textarea id="o-notes" rows={2} value={form.important_notes} onChange={set("important_notes")} placeholder="Ej.: prefiere que lo llamen en la tarde" />
          </Field>
        </FormSection>

        {!editing && (
          <FormSection title="Autorizaciones">
            <div className="space-y-3 rounded-xl border border-gray-200 p-4 dark:border-gray-800">
              <Checkbox
                checked={consents.data_processing}
                onChange={(e) => setConsents((c) => ({ ...c, data_processing: e.target.checked }))}
                label={
                  <>
                    Autoriza el tratamiento de sus datos personales <span className="text-error-500">*</span>
                  </>
                }
                description="Ley 1581 de 2012 (Habeas Data). Sin esta autorización no se puede registrar."
              />
              <p className="pt-1 text-theme-xs font-medium text-gray-500">Acepta recibir recordatorios por:</p>
              <div className="flex flex-wrap gap-x-6 gap-y-2">
                {(["whatsapp", "sms", "email"] as const).map((channel) => (
                  <Checkbox
                    key={channel}
                    checked={consents[channel]}
                    onChange={(e) => setConsents((c) => ({ ...c, [channel]: e.target.checked }))}
                    label={{ whatsapp: "WhatsApp", sms: "SMS", email: "Correo" }[channel]}
                  />
                ))}
              </div>
            </div>
            {(fieldError("consents") ?? fieldError("consents.data_processing")) && (
              <p className="text-theme-xs text-error-500">{fieldError("consents") ?? fieldError("consents.data_processing")}</p>
            )}
          </FormSection>
        )}
      </form>
    </SidePanel>
  );
}
