"use client";

import { inputClass } from "@/components/form/fields";
import { api } from "@/lib/api";
import type { OwnerWithPets, Paginated } from "@/lib/types";
import { useQuery } from "@tanstack/react-query";
import { useDeferredValue, useState } from "react";

const labelClass = "mb-1.5 block text-theme-sm font-medium text-gray-700 dark:text-gray-400";

/**
 * Owner search + pet choice (agenda, tasks). Both optional: the caller says
 * why in `hint`.
 */
export default function OwnerPetPicker({
  owner,
  petId,
  onChange,
  error,
  hint,
  id = "owner-picker",
  autoFocus = false,
  withPet = true,
}: {
  owner: Pick<OwnerWithPets, "id" | "full_name"> | null;
  petId: string;
  onChange: (owner: Pick<OwnerWithPets, "id" | "full_name"> | null, petId: string) => void;
  error?: string;
  hint?: string;
  id?: string;
  /** Only when the search is the first thing to fill in (the agenda), never in longer forms. */
  autoFocus?: boolean;
  /** false: only the owner (e.g. a sale), no pet selector. */
  withPet?: boolean;
}) {
  const [term, setTerm] = useState("");
  const query = useDeferredValue(term.trim());

  const results = useQuery({
    queryKey: ["owners", "search", query],
    queryFn: ({ signal }) => api<Paginated<OwnerWithPets>>(`owners?q=${encodeURIComponent(query)}`, { signal }).then((r) => r.data),
    enabled: !owner && query.length >= 2,
    staleTime: 30_000,
  });

  const selected = useQuery({
    queryKey: ["owners", owner?.id],
    queryFn: () => api<{ data: OwnerWithPets }>(`owners/${owner!.id}`).then((r) => r.data),
    enabled: !!owner,
  });

  if (owner) {
    const pets = (selected.data?.pets ?? []).filter((p) => !("deceased_at" in p) || !(p as { deceased_at: string | null }).deceased_at);

    return (
      <div className={withPet ? "grid gap-4 sm:grid-cols-2" : ""}>
        <div>
          <span className={labelClass}>Tutor</span>
          <div className="flex h-11 items-center justify-between rounded-lg border border-gray-200 bg-gray-50 px-3 dark:border-gray-800 dark:bg-white/3">
            <span className="truncate text-theme-sm font-medium text-gray-800 dark:text-white/90">{owner.full_name}</span>
            <button type="button" onClick={() => onChange(null, "")} className="text-theme-xs font-medium text-brand-600 hover:underline">
              Cambiar
            </button>
          </div>
        </div>
        {withPet && (
        <div>
          <label htmlFor={`${id}-pet`} className={labelClass}>
            Mascota
          </label>
          <select id={`${id}-pet`} className={inputClass} value={petId} onChange={(e) => onChange(owner, e.target.value)}>
            <option value="">{selected.isLoading ? "Cargando…" : "Elige la mascota"}</option>
            {pets.map((p) => (
              <option key={p.id} value={p.id}>
                {p.name}
                {p.species ? ` · ${p.species.name}` : ""}
              </option>
            ))}
          </select>
        </div>
        )}
        {error && <p className="text-theme-xs text-error-500 sm:col-span-2">{error}</p>}
      </div>
    );
  }

  return (
    <div className="relative">
      <label htmlFor={id} className={labelClass}>
        Tutor o mascota {hint && <span className="font-normal text-gray-400">({hint})</span>}
      </label>
      <input
        id={id}
        className={inputClass}
        value={term}
        onChange={(e) => setTerm(e.target.value)}
        placeholder="Ej.: Luz Marina, 1098…, Kira"
        autoComplete="off"
        autoFocus={autoFocus}
      />
      {query.length >= 2 && (
        <ul className="absolute inset-x-0 top-full z-10 mt-1 max-h-64 overflow-y-auto rounded-lg border border-gray-200 bg-white py-1 shadow-theme-lg dark:border-gray-800 dark:bg-gray-900">
          {results.isLoading && <li className="px-3 py-2 text-theme-sm text-gray-500">Buscando…</li>}
          {results.data?.length === 0 && <li className="px-3 py-2 text-theme-sm text-gray-500">Sin resultados. Créalo primero en Tutores y mascotas.</li>}
          {results.data?.map((o) => (
            <li key={o.id}>
              <button
                type="button"
                onClick={() => onChange({ id: o.id, full_name: o.full_name }, matchingPet(o, query))}
                className="w-full px-3 py-2 text-left hover:bg-gray-50 dark:hover:bg-white/5"
              >
                <span className="block text-theme-sm font-medium text-gray-800 dark:text-white/90">{o.full_name}</span>
                <span className="block truncate text-theme-xs text-gray-500">
                  {[o.document_number, o.mobile_phone].filter(Boolean).join(" · ")}
                  {o.pets.length > 0 && ` · ${o.pets.map((p) => p.name).join(", ")}`}
                </span>
              </button>
            </li>
          ))}
        </ul>
      )}
    </div>
  );
}

/** The pet the search was about: the only one, or the one whose name matches the typed term. */
function matchingPet(owner: OwnerWithPets, term: string): string {
  if (owner.pets.length === 1) return owner.pets[0].id;
  const plain = (v: string) => v.normalize("NFD").replace(/\p{Diacritic}/gu, "").toLowerCase();
  const words = plain(term).split(/\s+/);
  return owner.pets.find((p) => words.some((w) => w.length >= 2 && plain(p.name).startsWith(w)))?.id ?? "";
}
