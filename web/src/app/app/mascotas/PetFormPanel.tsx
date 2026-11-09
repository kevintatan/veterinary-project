"use client";

import SidePanel from "@/components/common/SidePanel";
import { Checkbox, Field, FormSection, Input, Select, Textarea } from "@/components/form/fields";
import PetPhotoUploader, { PHOTO_ACCEPT } from "@/components/domain/PetPhotoUploader";
import Button from "@/components/ui/button/Button";
import { api, ApiError } from "@/lib/api";
import { type Pet, REPRODUCTIVE_STATUS, SEX, SIZE, TEMPERAMENT } from "@/lib/patients";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useEffect, useRef, useState } from "react";

type CatalogItem = { id: string; name: string; code?: string; is_active: boolean };
const OTHER_BREED = "__other__";

/**
 * Create or edit a pet. Birth date can be exact or "about N years" (common at
 * the front desk), which stores an estimated date.
 */
export default function PetFormPanel({
  ownerId,
  ownerName,
  pet,
  onClose,
  onSaved,
}: {
  ownerId: string;
  ownerName: string;
  pet?: Pet;
  onClose: () => void;
  onSaved?: (pet: Pet) => void;
}) {
  const queryClient = useQueryClient();
  const editing = !!pet;

  const species = useQuery({
    queryKey: ["catalogs", "species"],
    queryFn: () => api<{ data: CatalogItem[] }>("catalogs/species").then((r) => r.data.filter((s) => s.is_active)),
    staleTime: 10 * 60_000,
  });

  const [form, setForm] = useState({
    name: pet?.name ?? "",
    species_id: pet?.species?.id ?? "",
    breed_id: pet?.breed?.id ?? (pet?.breed_other ? OTHER_BREED : ""),
    breed_other: pet?.breed_other ?? "",
    sex: pet?.sex?.value ?? "unknown",
    birth_date: pet?.birth_date ?? "",
    color: pet?.color ?? "",
    size: pet?.size?.value ?? "",
    reproductive_status: pet?.reproductive_status?.value ?? "unknown",
    temperament: pet?.temperament?.value ?? "",
    weight_kg: pet?.weight_kg?.toString() ?? "",
    microchip: pet?.microchip ?? "",
    diet: pet?.diet ?? "",
    important_notes: pet?.important_notes ?? "",
    deceased: !!pet?.deceased_at,
    deceased_at: pet?.deceased_at ?? "",
    deceased_reason: pet?.deceased_reason ?? "",
  });
  // New pet: the photo is chosen now and uploaded right after the pet is created.
  const [photo, setPhoto] = useState<File | null>(null);
  const [photoPreview, setPhotoPreview] = useState<string | null>(null);
  const photoInput = useRef<HTMLInputElement>(null);
  useEffect(() => () => {
    if (photoPreview) URL.revokeObjectURL(photoPreview);
  }, [photoPreview]);

  const [ageMode, setAgeMode] = useState<"date" | "approx">(pet?.birth_date_is_estimated ? "approx" : "date");
  const [approx, setApprox] = useState({ years: "", months: "" });
  const set = (key: keyof typeof form) => (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement>) =>
    setForm((f) => ({ ...f, [key]: e.target.value }));

  // Dog/cat first: they're 95 % of the visits.
  const speciesOptions = species.data ?? [];

  const breeds = useQuery({
    queryKey: ["catalogs", "breeds", form.species_id],
    queryFn: () => api<{ data: CatalogItem[] }>(`catalogs/breeds?species_id=${form.species_id}`).then((r) => r.data.filter((b) => b.is_active)),
    enabled: !!form.species_id,
    staleTime: 10 * 60_000,
  });

  const save = useMutation({
    mutationFn: () => {
      const approxDate = (() => {
        const y = Number(approx.years) || 0;
        const m = Number(approx.months) || 0;
        if (y === 0 && m === 0) return null;
        const d = new Date();
        d.setMonth(d.getMonth() - (y * 12 + m));
        return d.toISOString().slice(0, 10);
      })();
      const birthDate = ageMode === "date" ? form.birth_date || null : (approxDate ?? (pet?.birth_date_is_estimated ? pet.birth_date : null));

      const body = {
        owner_id: ownerId,
        name: form.name.trim(),
        species_id: form.species_id,
        breed_id: form.breed_id && form.breed_id !== OTHER_BREED ? form.breed_id : null,
        breed_other: form.breed_id === OTHER_BREED ? form.breed_other.trim() || null : null,
        sex: form.sex,
        birth_date: birthDate,
        birth_date_is_estimated: ageMode === "approx" && !!birthDate,
        color: form.color || null,
        size: form.size || null,
        reproductive_status: form.reproductive_status,
        temperament: form.temperament || null,
        weight_kg: form.weight_kg ? Number(form.weight_kg.replace(",", ".")) : null,
        microchip: form.microchip.trim() || null,
        diet: form.diet || null,
        important_notes: form.important_notes || null,
        ...(editing
          ? { deceased_at: form.deceased ? form.deceased_at || null : null, deceased_reason: form.deceased ? form.deceased_reason || null : null }
          : {}),
      };

      if (editing) return api<{ data: Pet }>(`pets/${pet.id}`, { method: "PATCH", body });

      return api<{ data: Pet }>("pets", { method: "POST", body }).then(async (created) => {
        if (photo) {
          const form = new FormData();
          form.append("photo", photo);
          // The pet is already saved: a failed photo shouldn't lose it (it can be uploaded later from the record).
          await api(`pets/${created.data.id}/photo`, { method: "POST", body: form }).catch(() => undefined);
        }
        return created;
      });
    },
    onSuccess: ({ data }) => {
      queryClient.invalidateQueries({ queryKey: ["owners"] });
      queryClient.invalidateQueries({ queryKey: ["pets", data.id] });
      // Close first: the parent's onClose may touch the URL, then onSaved navigates.
      onClose();
      onSaved?.(data);
    },
  });

  const error = save.error instanceof ApiError ? save.error : null;
  const fieldError = (name: string) => error?.field(name);
  const canSubmit = form.name.trim().length > 0 && !!form.species_id && !save.isPending;

  return (
    <SidePanel
      title={editing ? `Editar a ${pet.name}` : "Nueva mascota"}
      description={`Tutor: ${ownerName}`}
      onClose={onClose}
      footer={
        <div className="flex items-center justify-between gap-3">
          <p className="text-theme-xs text-error-500">{error ? (Object.keys(error.errors).length ? "Revisa los campos marcados." : error.message) : ""}</p>
          <div className="flex gap-3">
            <Button variant="outline" size="sm" onClick={onClose}>
              Cancelar
            </Button>
            <Button size="sm" type="submit" form="pet-form" disabled={!canSubmit} loading={save.isPending}>
              {editing ? "Guardar cambios" : "Registrar mascota"}
            </Button>
          </div>
        </div>
      }
    >
      <form
        id="pet-form"
        className="space-y-6"
        onSubmit={(e) => {
          e.preventDefault();
          if (canSubmit) save.mutate();
        }}
      >
        <FormSection title="Foto">
          {editing ? (
            <PetPhotoUploader petId={pet.id} name={pet.name} photoUrl={pet.photo_url} speciesCode={pet.species?.code} size="md" layout="inline" />
          ) : (
            <div className="flex items-center gap-3">
              <button
                type="button"
                onClick={() => photoInput.current?.click()}
                className="flex h-14 w-14 shrink-0 items-center justify-center overflow-hidden rounded-2xl border-2 border-dashed border-gray-300 text-gray-400 hover:border-brand-400 dark:border-gray-700"
                aria-label="Elegir foto"
              >
                {photoPreview ? (
                  // eslint-disable-next-line @next/next/no-img-element -- local preview (object URL)
                  <img src={photoPreview} alt="" className="h-full w-full object-cover" />
                ) : (
                  <span className="text-xl">+</span>
                )}
              </button>
              <div className="flex flex-col gap-1">
                <button type="button" onClick={() => photoInput.current?.click()} className="w-fit text-theme-sm font-medium text-brand-700 hover:underline dark:text-brand-300">
                  {photo ? "Cambiar foto" : "Elegir foto"}
                </button>
                <span className="text-theme-xs text-gray-500">{photo ? photo.name : "Opcional. Sale en su ficha y en el carné."}</span>
              </div>
              <input
                ref={photoInput}
                type="file"
                accept={PHOTO_ACCEPT}
                className="hidden"
                onChange={(e) => {
                  const file = e.target.files?.[0] ?? null;
                  setPhoto(file);
                  setPhotoPreview(file ? URL.createObjectURL(file) : null);
                  e.target.value = "";
                }}
              />
            </div>
          )}
        </FormSection>

        <FormSection title="Identificación">
          <Field label="Nombre" htmlFor="p-name" error={fieldError("name")}>
            <Input id="p-name" value={form.name} onChange={set("name")} autoFocus={!editing} required maxLength={80} />
          </Field>

          <div>
            <span className="mb-1.5 block text-theme-sm font-medium text-gray-700 dark:text-gray-400">Especie</span>
            <div className="flex flex-wrap gap-2" role="radiogroup" aria-label="Especie">
              {speciesOptions.map((s) => (
                <button
                  key={s.id}
                  type="button"
                  role="radio"
                  aria-checked={form.species_id === s.id}
                  onClick={() => setForm((f) => ({ ...f, species_id: s.id, breed_id: "", breed_other: "" }))}
                  className={
                    form.species_id === s.id
                      ? "rounded-lg border border-brand-500 bg-brand-50 px-3 py-2 text-theme-sm font-medium text-brand-700 dark:bg-brand-500/15 dark:text-brand-300"
                      : "rounded-lg border border-gray-200 px-3 py-2 text-theme-sm text-gray-700 hover:border-gray-300 dark:border-gray-700 dark:text-gray-300"
                  }
                >
                  {s.name}
                </button>
              ))}
            </div>
            {fieldError("species_id") && <p className="mt-1 text-theme-xs text-error-500">{fieldError("species_id")}</p>}
          </div>

          {form.species_id && (
            <div className="grid gap-3 sm:grid-cols-2">
              <Field label="Raza" htmlFor="p-breed" optional>
                <Select id="p-breed" value={form.breed_id} onChange={set("breed_id")}>
                  <option value="">{breeds.isLoading ? "Cargando…" : "Sin raza definida / criollo"}</option>
                  {breeds.data?.map((b) => (
                    <option key={b.id} value={b.id}>
                      {b.name}
                    </option>
                  ))}
                  <option value={OTHER_BREED}>Otra (escribir)</option>
                </Select>
              </Field>
              {form.breed_id === OTHER_BREED && (
                <Field label="¿Cuál raza?" htmlFor="p-breed-other">
                  <Input id="p-breed-other" value={form.breed_other} onChange={set("breed_other")} maxLength={80} />
                </Field>
              )}
            </div>
          )}

          <div className="grid gap-3 sm:grid-cols-2">
            <Field label="Sexo" htmlFor="p-sex">
              <Select id="p-sex" value={form.sex} onChange={set("sex")}>
                {SEX.map(([v, l]) => (
                  <option key={v} value={v}>
                    {l}
                  </option>
                ))}
              </Select>
            </Field>
            <Field label="Estado reproductivo" htmlFor="p-repro">
              <Select id="p-repro" value={form.reproductive_status} onChange={set("reproductive_status")}>
                {REPRODUCTIVE_STATUS.map(([v, l]) => (
                  <option key={v} value={v}>
                    {l}
                  </option>
                ))}
              </Select>
            </Field>
          </div>

          <div>
            <div className="mb-1.5 flex items-center justify-between">
              <span className="text-theme-sm font-medium text-gray-700 dark:text-gray-400">Edad</span>
              <button type="button" onClick={() => setAgeMode((m) => (m === "date" ? "approx" : "date"))} className="text-theme-xs font-medium text-brand-600 hover:underline dark:text-brand-400">
                {ageMode === "date" ? "No sé la fecha exacta" : "Tengo la fecha de nacimiento"}
              </button>
            </div>
            {ageMode === "date" ? (
              <Input type="date" aria-label="Fecha de nacimiento" value={form.birth_date} onChange={set("birth_date")} max={new Date().toISOString().slice(0, 10)} invalid={!!fieldError("birth_date")} />
            ) : (
              <div className="grid grid-cols-2 gap-3">
                <Input type="number" min={0} max={40} aria-label="Años aproximados" placeholder={pet?.age ? `Hoy: ${pet.age}` : "Años"} value={approx.years} onChange={(e) => setApprox((a) => ({ ...a, years: e.target.value }))} />
                <Input type="number" min={0} max={11} aria-label="Meses" placeholder="Meses" value={approx.months} onChange={(e) => setApprox((a) => ({ ...a, months: e.target.value }))} />
              </div>
            )}
            {fieldError("birth_date") && <p className="mt-1 text-theme-xs text-error-500">{fieldError("birth_date")}</p>}
          </div>
        </FormSection>

        <FormSection title="Características">
          <div className="grid gap-3 sm:grid-cols-3">
            <Field label="Peso (kg)" htmlFor="p-weight" optional error={fieldError("weight_kg")}>
              <Input id="p-weight" inputMode="decimal" value={form.weight_kg} onChange={set("weight_kg")} placeholder="12,5" />
            </Field>
            <Field label="Tamaño" htmlFor="p-size" optional>
              <Select id="p-size" value={form.size} onChange={set("size")}>
                <option value="">—</option>
                {SIZE.map(([v, l]) => (
                  <option key={v} value={v}>
                    {l}
                  </option>
                ))}
              </Select>
            </Field>
            <Field label="Color" htmlFor="p-color" optional>
              <Input id="p-color" value={form.color} onChange={set("color")} maxLength={60} />
            </Field>
          </div>
          <div className="grid gap-3 sm:grid-cols-2">
            <Field label="Temperamento" htmlFor="p-temper" optional>
              <Select id="p-temper" value={form.temperament} onChange={set("temperament")}>
                <option value="">—</option>
                {TEMPERAMENT.map(([v, l]) => (
                  <option key={v} value={v}>
                    {l}
                  </option>
                ))}
              </Select>
            </Field>
            <Field label="Microchip" htmlFor="p-chip" optional error={fieldError("microchip")}>
              <Input id="p-chip" value={form.microchip} onChange={set("microchip")} inputMode="numeric" maxLength={30} invalid={!!fieldError("microchip")} />
            </Field>
          </div>
          <Field label="Alimentación" htmlFor="p-diet" optional>
            <Input id="p-diet" value={form.diet} onChange={set("diet")} placeholder="Ej.: Pro Plan Adulto, 2 veces al día" maxLength={120} />
          </Field>
          <Field label="Notas importantes" htmlFor="p-notes" optional hint="Las alergias y alertas se registran aparte, en la ficha.">
            <Textarea id="p-notes" rows={2} value={form.important_notes} onChange={set("important_notes")} />
          </Field>
        </FormSection>

        {editing && (
          <FormSection title="Fallecimiento">
            <Checkbox checked={form.deceased} onChange={(e) => setForm((f) => ({ ...f, deceased: e.target.checked }))} label="La mascota falleció" description="Deja de aparecer al agendar y en recordatorios. Su historia se conserva." />
            {form.deceased && (
              <div className="grid gap-3 sm:grid-cols-2">
                <Field label="Fecha" htmlFor="p-dec-at" error={fieldError("deceased_at")}>
                  <Input id="p-dec-at" type="date" value={form.deceased_at} onChange={set("deceased_at")} max={new Date().toISOString().slice(0, 10)} />
                </Field>
                <Field label="Causa" htmlFor="p-dec-reason" optional>
                  <Input id="p-dec-reason" value={form.deceased_reason} onChange={set("deceased_reason")} maxLength={255} />
                </Field>
              </div>
            )}
          </FormSection>
        )}
      </form>
    </SidePanel>
  );
}
