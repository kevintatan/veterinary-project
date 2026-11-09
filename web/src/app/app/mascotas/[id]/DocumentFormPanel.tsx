"use client";

import SidePanel from "@/components/common/SidePanel";
import { Checkbox, Field, Input, Select, Textarea } from "@/components/form/fields";
import { useSession } from "@/components/providers/SessionProvider";
import Button from "@/components/ui/button/Button";
import { ApiError, api } from "@/lib/api";
import type { DocumentField, DocumentTypeDef, PetDocument } from "@/lib/documents";
import type { Member } from "@/lib/settings";
import { cn } from "@/utils";
import { useMutation, useQuery } from "@tanstack/react-query";
import { useRouter } from "next/navigation";
import { useState } from "react";

type Value = string | number | boolean | null;

/**
 * New consent (or edit a draft): pick the type, fill only the case fields
 * (owner, pet, vet and clinic data fill themselves), then go to the document
 * to read it with the owner and sign.
 */
export default function DocumentFormPanel({ petId, editing, onClose, onSaved }: { petId: string; editing?: PetDocument; onClose: () => void; onSaved?: (doc: PetDocument) => void }) {
  const router = useRouter();
  const { user } = useSession();
  const [code, setCode] = useState(editing?.type ?? "");
  const [data, setData] = useState<Record<string, Value>>(editing?.data ?? {});
  const [vetId, setVetId] = useState(editing?.vet?.id ?? user.id);
  const [withWitness, setWithWitness] = useState(editing?.with_witness ?? false);

  const types = useQuery({ queryKey: ["document-types"], queryFn: () => api<{ data: DocumentTypeDef[] }>("document-types").then((r) => r.data), staleTime: Infinity });
  const members = useQuery({ queryKey: ["members"], queryFn: () => api<{ data: Member[] }>("members").then((r) => r.data) });
  const vets = (members.data ?? []).filter((m) => m.status === "active" && ["veterinarian", "admin"].includes(m.role.name));
  const type = types.data?.find((t) => t.code === code);

  const save = useMutation({
    mutationFn: () =>
      api<{ data: PetDocument }>(editing ? `documents/${editing.id}` : `pets/${petId}/documents`, {
        method: editing ? "PATCH" : "POST",
        body: { type: code, vet_id: vetId, with_witness: withWitness, data },
      }).then((r) => r.data),
    onSuccess: (doc) => {
      onSaved?.(doc);
      if (!editing) router.push(`/app/documentos/${doc.id}`);
      onClose();
    },
  });

  const error = save.error instanceof ApiError ? save.error : null;
  const fieldError = (key: string) => error?.field(`data.${key}`);
  const general = error && (error.field("owner") ?? error.field("vet_id") ?? error.field("type") ?? (Object.keys(error.errors).length ? null : error.message));

  return (
    <SidePanel
      title={editing ? "Editar documento" : "Nuevo documento"}
      description="Los datos del tutor, la mascota, el veterinario y la clínica se llenan solos."
      onClose={onClose}
      width="max-w-xl"
      footer={
        <div className="flex justify-end gap-2">
          <Button variant="outline" size="sm" onClick={onClose}>
            Cancelar
          </Button>
          <Button size="sm" type="submit" form="document-form" loading={save.isPending} disabled={!type}>
            {editing ? "Guardar cambios" : "Crear y revisar"}
          </Button>
        </div>
      }
    >
      <form
        id="document-form"
        className="space-y-5"
        onSubmit={(e) => {
          e.preventDefault();
          if (type) save.mutate();
        }}
      >
        {general && <p className="rounded-lg bg-error-50 px-4 py-3 text-theme-sm text-error-700 dark:bg-error-500/10 dark:text-error-400">{general}</p>}

        {!editing && (
          <fieldset className="space-y-2">
            <legend className="mb-1.5 text-theme-sm font-medium text-gray-700 dark:text-gray-400">Tipo de documento</legend>
            {(types.data ?? []).map((t) => (
              <label
                key={t.code}
                className={cn(
                  "flex cursor-pointer gap-3 rounded-xl border px-4 py-3 transition",
                  code === t.code ? "border-brand-500 bg-brand-25 dark:bg-brand-500/10" : "border-gray-200 hover:border-gray-300 dark:border-gray-800",
                )}
              >
                <input type="radio" name="type" value={t.code} checked={code === t.code} onChange={() => (setCode(t.code), setData({}), setWithWitness(false))} className="mt-1 accent-brand-500" />
                <span>
                  <span className="block text-theme-sm font-semibold text-gray-800 dark:text-white/90">{t.short}</span>
                  <span className="block text-theme-xs text-gray-500">{t.description}</span>
                </span>
              </label>
            ))}
          </fieldset>
        )}

        {type && (
          <>
            <Field label="Médico veterinario responsable" htmlFor="doc-vet" hint="Firma desde su propia sesión. Necesita tarjeta profesional registrada." error={error?.field("vet_id")}>
              <Select id="doc-vet" value={vetId} onChange={(e) => setVetId(e.target.value)}>
                {vets.length === 0 && <option value={vetId}>{user.name}</option>}
                {vets.map((m) => (
                  <option key={m.user.id} value={m.user.id}>
                    {m.user.name}
                    {m.user.professional_license ? ` · T.P. ${m.user.professional_license}` : " · sin tarjeta profesional"}
                  </option>
                ))}
              </Select>
            </Field>

            {type.fields.map((f) => (
              <FieldInput key={f.key} field={f} value={data[f.key] ?? null} onChange={(v) => setData((d) => ({ ...d, [f.key]: v }))} error={fieldError(f.key)} />
            ))}

            {type.allows_witness && (
              <Checkbox label="Firma también un testigo" description="Recomendado en decisiones delicadas. Si lo marcas, el documento no queda firmado hasta que el testigo firme." checked={withWitness} onChange={(e) => setWithWitness(e.target.checked)} />
            )}
          </>
        )}
      </form>
    </SidePanel>
  );
}

function FieldInput({ field: f, value, onChange, error }: { field: DocumentField; value: Value; onChange: (v: Value) => void; error?: string }) {
  const id = `doc-${f.key}`;

  if (f.type === "checkbox") {
    return <Checkbox label={f.label} description={f.help} checked={Boolean(value)} onChange={(e) => onChange(e.target.checked)} />;
  }

  return (
    <Field label={f.label} htmlFor={id} optional={!f.required} hint={f.help} error={error}>
      {f.type === "textarea" ? (
        <Textarea id={id} rows={3} value={(value as string) ?? ""} onChange={(e) => onChange(e.target.value)} placeholder={f.placeholder} />
      ) : f.type === "select" ? (
        <Select id={id} value={(value as string) ?? ""} onChange={(e) => onChange(e.target.value || null)} invalid={!!error}>
          <option value="">Elige una opción</option>
          {Object.entries(f.options ?? {}).map(([k, label]) => (
            <option key={k} value={k}>
              {label}
            </option>
          ))}
        </Select>
      ) : f.type === "money" ? (
        <div className="relative">
          <span className="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-theme-sm text-gray-500">$</span>
          <Input
            id={id}
            inputMode="numeric"
            className="pl-7 tabular"
            value={value === null || value === "" ? "" : Number(value).toLocaleString("es-CO")}
            onChange={(e) => {
              const digits = e.target.value.replace(/\D/g, "");
              onChange(digits === "" ? null : Number(digits));
            }}
            invalid={!!error}
          />
        </div>
      ) : (
        <Input
          id={id}
          type={f.type === "number" ? "number" : "text"}
          min={f.type === "number" ? 1 : undefined}
          value={(value as string | number) ?? ""}
          onChange={(e) => onChange(f.type === "number" ? (e.target.value === "" ? null : Number(e.target.value)) : e.target.value)}
          placeholder={f.placeholder}
          invalid={!!error}
        />
      )}
    </Field>
  );
}
