"use client";

import { Card, EmptyState, Skeleton } from "@/components/common/Card";
import PageHeader from "@/components/common/PageHeader";
import PetAvatar from "@/components/domain/PetAvatar";
import { inputClass } from "@/components/form/fields";
import { useCan } from "@/components/providers/SessionProvider";
import Button from "@/components/ui/button/Button";
import { api } from "@/lib/api";
import { formatPhone, initials, type Owner } from "@/lib/patients";
import type { Paginated } from "@/lib/types";
import { cn } from "@/utils";
import { keepPreviousData, useQuery } from "@tanstack/react-query";
import Link from "next/link";
import { usePathname, useRouter, useSearchParams } from "next/navigation";
import { useDeferredValue, useEffect, useState } from "react";
import OwnerFormPanel from "./OwnerFormPanel";

/**
 * Owners directory. Search by name (typo tolerant), document, phone or pet
 * name/microchip — the same search the header box (Ctrl K) sends here.
 */
export default function OwnersList() {
  const router = useRouter();
  const pathname = usePathname();
  const params = useSearchParams();
  const canManage = useCan("patients.manage");

  const [term, setTerm] = useState(params.get("q") ?? "");
  const query = useDeferredValue(term.trim());
  const page = Math.max(1, Number(params.get("pagina")) || 1);
  const [creating, setCreating] = useState(params.get("nuevo") === "1");

  // Keep the URL in sync so the search survives reloads and back/forward.
  useEffect(() => {
    const next = new URLSearchParams();
    if (query) next.set("q", query);
    if (page > 1 && query === (params.get("q") ?? "")) next.set("pagina", String(page));
    const target = next.toString();
    if (target !== params.toString()) router.replace(`${pathname}${target ? `?${target}` : ""}`, { scroll: false });
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [query]);

  // The header search box navigates here with a new ?q=.
  const urlQuery = params.get("q") ?? "";
  const [lastUrlQuery, setLastUrlQuery] = useState(urlQuery);
  if (urlQuery !== lastUrlQuery) {
    setLastUrlQuery(urlQuery);
    setTerm(urlQuery);
  }

  const owners = useQuery({
    queryKey: ["owners", "list", query, page],
    queryFn: ({ signal }) => api<Paginated<Owner>>(`owners?q=${encodeURIComponent(query)}&page=${page}`, { signal }),
    placeholderData: keepPreviousData,
  });

  const list = owners.data?.data ?? [];
  const meta = owners.data?.meta;

  return (
    <>
      <PageHeader
        title="Tutores y mascotas"
        subtitle={meta ? `${meta.total.toLocaleString("es-CO")} ${query ? "resultados" : "tutores registrados"}` : " "}
        actions={
          canManage && (
            <Button size="sm" onClick={() => setCreating(true)}>
              Nuevo tutor
            </Button>
          )
        }
      />

      <Card>
        <div className="border-b border-gray-100 p-4 dark:border-gray-800">
          <div className="relative max-w-xl">
            <svg className="pointer-events-none absolute top-1/2 left-3.5 -translate-y-1/2 text-gray-400" width="18" height="18" viewBox="0 0 20 20" fill="none" aria-hidden>
              <circle cx="9" cy="9" r="6" stroke="currentColor" strokeWidth="1.6" />
              <path d="m14 14 3 3" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" />
            </svg>
            <input
              type="search"
              value={term}
              onChange={(e) => setTerm(e.target.value)}
              placeholder="Nombre, documento, celular, mascota o microchip"
              aria-label="Buscar tutores y mascotas"
              className={cn(inputClass, "ps-10")}
              autoFocus
            />
          </div>
        </div>

        <div className={cn("transition-opacity", owners.isFetching && !owners.isLoading && "opacity-60")}>
          {owners.isLoading ? (
            <div className="space-y-3 p-4">
              {[1, 2, 3, 4, 5].map((i) => (
                <Skeleton key={i} className="h-14" />
              ))}
            </div>
          ) : owners.isError ? (
            <EmptyState title="No fue posible cargar los tutores." description="Intenta de nuevo en un momento." />
          ) : list.length === 0 ? (
            <EmptyState
              title={query ? `Sin resultados para “${query}”.` : "Aún no hay tutores registrados."}
              description={canManage ? "Registra el tutor y luego sus mascotas." : undefined}
            />
          ) : (
            <div className="overflow-x-auto">
              <table className="w-full min-w-[720px] text-left text-theme-sm">
                <thead>
                  <tr className="border-b border-gray-100 text-theme-xs text-gray-500 dark:border-gray-800 dark:text-gray-400">
                    <th className="px-5 py-3 font-medium">Tutor</th>
                    <th className="px-5 py-3 font-medium">Contacto</th>
                    <th className="px-5 py-3 font-medium">Mascotas</th>
                  </tr>
                </thead>
                <tbody>
                  {list.map((owner) => (
                    <tr key={owner.id} className="group relative border-b border-gray-100 last:border-0 hover:bg-gray-50 dark:border-gray-800 dark:hover:bg-white/2">
                      <td className="px-5 py-3">
                        <div className="flex items-center gap-3">
                          <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-gray-100 text-theme-xs font-semibold text-gray-600 dark:bg-white/5 dark:text-gray-300">
                            {initials(owner.full_name)}
                          </span>
                          <div className="min-w-0">
                            {/* Whole-row link: the anchor's ::after covers the row. */}
                            <Link href={`/app/tutores/${owner.id}`} className="font-medium text-gray-800 after:absolute after:inset-0 group-hover:text-brand-700 dark:text-white/90 dark:group-hover:text-brand-300">
                              {owner.full_name}
                            </Link>
                            <p className="text-theme-xs text-gray-500">
                              {owner.document_number ? `${owner.document_type?.value ?? ""} ${owner.document_number}` : "Sin documento"}
                            </p>
                          </div>
                        </div>
                      </td>
                      <td className="px-5 py-3 text-gray-600 tabular dark:text-gray-400">
                        {formatPhone(owner.mobile_phone) || <span className="text-gray-400">—</span>}
                        {owner.email && <p className="truncate text-theme-xs text-gray-500">{owner.email}</p>}
                      </td>
                      <td className="px-5 py-3">
                        {owner.pets.length === 0 ? (
                          <span className="text-theme-xs text-gray-400">Sin mascotas</span>
                        ) : (
                          <div className="flex flex-wrap gap-1.5">
                            {owner.pets.map((pet) => (
                              <span
                                key={pet.id}
                                className={cn(
                                  "inline-flex items-center gap-1.5 rounded-full border border-gray-200 py-0.5 ps-0.5 pe-2.5 text-theme-xs dark:border-gray-700",
                                  pet.deceased_at ? "text-gray-400" : "text-gray-700 dark:text-gray-300",
                                )}
                              >
                                <PetAvatar photoUrl={pet.photo_url} speciesCode={pet.species?.code} size="sm" className="h-6 w-6 rounded-full" />
                                {pet.name}
                                {pet.deceased_at && " †"}
                              </span>
                            ))}
                          </div>
                        )}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </div>

        {meta && meta.last_page > 1 && (
          <div className="flex items-center justify-between border-t border-gray-100 px-5 py-3 text-theme-sm text-gray-500 dark:border-gray-800">
            <span>
              Página {meta.current_page} de {meta.last_page}
            </span>
            <div className="flex gap-2">
              <PageLink page={page - 1} disabled={page <= 1} query={query}>
                Anterior
              </PageLink>
              <PageLink page={page + 1} disabled={page >= meta.last_page} query={query}>
                Siguiente
              </PageLink>
            </div>
          </div>
        )}
      </Card>

      {creating && (
        <OwnerFormPanel
          initialName={/\d/.test(query) ? "" : query}
          onClose={() => setCreating(false)}
          onSaved={(owner) => router.push(`/app/tutores/${owner.id}?nuevo=1`)}
        />
      )}
    </>
  );
}

function PageLink({ page, disabled, query, children }: { page: number; disabled: boolean; query: string; children: React.ReactNode }) {
  const href = `?${new URLSearchParams({ ...(query ? { q: query } : {}), ...(page > 1 ? { pagina: String(page) } : {}) }).toString()}`;
  return disabled ? (
    <span className="rounded-lg border border-gray-200 px-3 py-1.5 text-gray-300 dark:border-gray-800 dark:text-gray-600">{children}</span>
  ) : (
    <Link href={href} scroll={false} className="rounded-lg border border-gray-200 px-3 py-1.5 text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/5">
      {children}
    </Link>
  );
}
