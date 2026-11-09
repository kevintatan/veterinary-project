"use client";

import { Card, EmptyState, Skeleton } from "@/components/common/Card";
import PageHeader from "@/components/common/PageHeader";
import { Input, Select } from "@/components/form/fields";
import { useCan, useSession } from "@/components/providers/SessionProvider";
import Button from "@/components/ui/button/Button";
import { api } from "@/lib/api";
import { type Alerts, inventoryKeys, KINDS, type ProductDetail, type ProductRow, qty, qtyUnit, STOCK_STATUS } from "@/lib/inventory";
import { formatCop } from "@/lib/settings";
import { cn } from "@/utils";
import { keepPreviousData, useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { usePathname, useRouter, useSearchParams } from "next/navigation";
import { useDeferredValue, useState } from "react";
import ImportModal from "./ImportModal";
import MovementModal, { type MovementMode } from "./MovementModal";
import ProductFormPanel from "./ProductFormPanel";
import ProductPanel from "./ProductPanel";

type Moving = { mode: MovementMode; product: { id: string; name: string; unit: { label: string } | string }; stock: number; lot?: { movementId: string; label: string; quantity: number } };

/**
 * Inventario (docs/inventario.md): products with their stock in the chosen
 * branch, and what needs attention (low stock, lots about to expire).
 */
export default function InventoryView() {
  const { branches } = useSession();
  const params = useSearchParams();
  const router = useRouter();
  const pathname = usePathname();
  const queryClient = useQueryClient();
  const canManage = useCan("inventory.manage");
  const canAdjust = useCan("inventory.adjust");

  const branchId = branches.some((b) => b.id === params.get("sede")) ? (params.get("sede") as string) : (branches[0]?.id ?? "");
  const branchName = branches.find((b) => b.id === branchId)?.name ?? "";
  const tab = params.get("vista") === "alertas" ? "alerts" : "products";
  const setParam = (key: string, value: string | null) => {
    const next = new URLSearchParams(params);
    if (value === null) next.delete(key);
    else next.set(key, value);
    router.replace(`${pathname}?${next}`, { scroll: false });
  };

  const [q, setQ] = useState("");
  const [kind, setKind] = useState("");
  const [status, setStatus] = useState<"" | "low" | "inactive">("");
  const search = useDeferredValue(q.trim());
  const filters = { q: search, kind, status };

  const products = useQuery({
    queryKey: inventoryKeys.products(branchId, filters),
    queryFn: () => {
      const query = new URLSearchParams({ branch_id: branchId, ...(search ? { q: search } : {}), ...(kind ? { kind } : {}), ...(status ? { status } : {}) });
      return api<{ data: ProductRow[]; meta: { show_costs: boolean } }>(`products?${query}`);
    },
    enabled: !!branchId,
    placeholderData: keepPreviousData,
  });
  const alerts = useQuery({
    queryKey: inventoryKeys.alerts(branchId),
    queryFn: () => api<{ data: Alerts }>(`inventory/alerts?branch_id=${branchId}`).then((r) => r.data),
    enabled: !!branchId,
  });
  const closeLot = useMutation({
    mutationFn: (movementId: string) => api(`inventory/lots/${movementId}/close`, { method: "POST" }),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: inventoryKeys.all }),
  });

  const showCosts = products.data?.meta.show_costs ?? false;
  const alertCount = (alerts.data?.low.length ?? 0) + (alerts.data?.expiring.length ?? 0);

  const [open, setOpen] = useState<string | null>(null);
  const [editing, setEditing] = useState<ProductRow | "new" | null>(null);
  const [moving, setMoving] = useState<Moving | null>(null);
  const [importing, setImporting] = useState(false);

  const startMove = (mode: MovementMode, p: ProductDetail) =>
    setMoving({ mode, product: p, stock: p.stocks.find((s) => s.branch.id === branchId)?.quantity ?? 0 });

  return (
    <>
      <PageHeader
        title="Inventario"
        subtitle="Productos, existencias por sede y lo que necesita atención."
        actions={
          <div className="flex flex-wrap items-center gap-2">
            {branches.length > 1 && (
              <Select aria-label="Sede" value={branchId} onChange={(e) => setParam("sede", e.target.value)} className="h-10 w-auto">
                {branches.map((b) => (
                  <option key={b.id} value={b.id}>
                    {b.name}
                  </option>
                ))}
              </Select>
            )}
            {canManage && (
              <>
                <Button variant="outline" size="sm" onClick={() => setImporting(true)}>
                  Importar desde Excel
                </Button>
                <Button size="sm" onClick={() => setEditing("new")}>
                  Nuevo producto
                </Button>
              </>
            )}
          </div>
        }
      />

      <div className="mb-4 flex gap-1 border-b border-gray-200 dark:border-gray-800" role="tablist">
        {(
          [
            ["products", "Productos", null],
            ["alerts", "Alertas", alertCount],
          ] as const
        ).map(([key, label, count]) => (
          <button
            key={key}
            type="button"
            role="tab"
            aria-selected={tab === key}
            onClick={() => setParam("vista", key === "alerts" ? "alertas" : null)}
            className={cn("-mb-px border-b-2 px-4 py-2.5 text-sm font-medium", tab === key ? "border-brand-500 text-brand-700 dark:text-brand-400" : "border-transparent text-gray-500 hover:text-gray-800 dark:hover:text-white")}
          >
            {label}
            {!!count && <span className="ml-2 rounded-full bg-warning-100 px-1.5 text-theme-xs font-semibold text-warning-800">{count}</span>}
          </button>
        ))}
      </div>

      {tab === "products" ? (
        <>
          <div className="mb-4 flex flex-wrap gap-3">
            <Input value={q} onChange={(e) => setQ(e.target.value)} placeholder="Buscar por nombre o código…" className="max-w-xs" aria-label="Buscar producto" />
            <Select value={kind} onChange={(e) => setKind(e.target.value)} className="w-auto" aria-label="Tipo">
              <option value="">Todos los tipos</option>
              {KINDS.map(([v, l]) => (
                <option key={v} value={v}>
                  {l}
                </option>
              ))}
            </Select>
            <Select value={status} onChange={(e) => setStatus(e.target.value as typeof status)} className="w-auto" aria-label="Estado">
              <option value="">Activos</option>
              <option value="low">Bajos, agotados o en negativo</option>
              <option value="inactive">Inactivos</option>
            </Select>
          </div>

          {products.isLoading ? (
            <Skeleton className="h-72" />
          ) : !products.data?.data.length ? (
            <Card>
              <EmptyState
                title={search || kind || status ? "Ningún producto coincide" : "Aún no hay productos"}
                description={search || kind || status ? "Prueba con otro filtro." : "Crea tus productos uno por uno o impórtalos desde Excel."}
              />
            </Card>
          ) : (
            <Card className="overflow-hidden">
              <div className="overflow-x-auto">
                <table className="w-full min-w-[640px] text-left text-theme-sm">
                  <thead className="border-b border-gray-100 bg-gray-50 text-theme-xs text-gray-500 uppercase dark:border-gray-800 dark:bg-white/2">
                    <tr>
                      <th className="px-5 py-3 font-medium">Producto</th>
                      <th className="px-5 py-3 text-right font-medium">Existencia{branches.length > 1 ? ` · ${branchName}` : ""}</th>
                      <th className="px-5 py-3 text-right font-medium">Mínimo</th>
                      <th className="px-5 py-3 text-right font-medium">Precio</th>
                      {showCosts && <th className="px-5 py-3 text-right font-medium">Costo</th>}
                      {branches.length > 1 && <th className="px-5 py-3 text-right font-medium">Todas las sedes</th>}
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-gray-100 dark:divide-gray-800">
                    {products.data.data.map((p) => {
                      const st = STOCK_STATUS[p.stock_status];
                      return (
                        <tr key={p.id} onClick={() => setOpen(p.id)} className="cursor-pointer hover:bg-gray-50 dark:hover:bg-white/2">
                          <td className="px-5 py-3">
                            <p className="font-medium text-gray-800 dark:text-white/90">{p.name}</p>
                            <p className="text-theme-xs text-gray-500">
                              {p.kind.label}
                              {p.sku ? ` · ${p.sku}` : ""}
                            </p>
                          </td>
                          <td className={cn("px-5 py-3 text-right font-semibold tabular-nums", st.tone)}>
                            {p.tracks_stock && p.stock_status !== "none" ? qtyUnit(p.stock, p.unit.value) : "—"}
                            {st.label && <span className="block text-theme-xs font-medium">{st.label}</span>}
                          </td>
                          <td className="px-5 py-3 text-right text-gray-500 tabular-nums">{p.tracks_stock && p.min_stock ? qty(p.min_stock) : "—"}</td>
                          <td className="px-5 py-3 text-right tabular-nums">{formatCop(p.sale_price)}</td>
                          {showCosts && <td className="px-5 py-3 text-right text-gray-500 tabular-nums">{p.cost_price !== null ? formatCop(p.cost_price) : "—"}</td>}
                          {branches.length > 1 && <td className="px-5 py-3 text-right text-gray-500 tabular-nums">{p.tracks_stock ? qty(p.stock_total) : "—"}</td>}
                        </tr>
                      );
                    })}
                  </tbody>
                </table>
              </div>
            </Card>
          )}
        </>
      ) : (
        <div className="grid gap-6 lg:grid-cols-2">
          <Card>
            <div className="border-b border-gray-100 px-5 py-4 dark:border-gray-800">
              <h3 className="text-base font-semibold text-gray-800 dark:text-white/90">Existencias bajas</h3>
              <p className="text-theme-sm text-gray-500">En el mínimo o por debajo, agotados o en negativo.</p>
            </div>
            {!alerts.data?.low.length ? (
              <p className="px-5 py-8 text-center text-theme-sm text-gray-500">Todo en orden.</p>
            ) : (
              <ul className="divide-y divide-gray-100 dark:divide-gray-800">
                {alerts.data.low.map((a) => (
                  <li key={a.product.id} className="flex items-center justify-between gap-3 px-5 py-3">
                    <button type="button" onClick={() => setOpen(a.product.id)} className="min-w-0 text-left">
                      <span className="block truncate text-theme-sm font-medium text-gray-800 hover:underline dark:text-white/90">{a.product.name}</span>
                      <span className={cn("text-theme-xs font-medium", STOCK_STATUS[a.status].tone)}>
                        {STOCK_STATUS[a.status].label}: {qty(a.quantity)}
                        {a.min_stock ? ` de ${qty(a.min_stock)} mínimo` : ""}
                      </span>
                    </button>
                    {canManage && (
                      <Button size="sm" variant="outline" onClick={() => setMoving({ mode: "purchase", product: a.product, stock: a.quantity })}>
                        Registrar entrada
                      </Button>
                    )}
                  </li>
                ))}
              </ul>
            )}
          </Card>

          <Card>
            <div className="border-b border-gray-100 px-5 py-4 dark:border-gray-800">
              <h3 className="text-base font-semibold text-gray-800 dark:text-white/90">Lotes por vencer</h3>
              <p className="text-theme-sm text-gray-500">Vencen en los próximos 60 días (o ya vencieron) y aún hay existencias.</p>
            </div>
            {!alerts.data?.expiring.length ? (
              <p className="px-5 py-8 text-center text-theme-sm text-gray-500">Nada por vencer.</p>
            ) : (
              <ul className="divide-y divide-gray-100 dark:divide-gray-800">
                {alerts.data.expiring.map((a) => {
                  const date = new Date(`${a.expires_on}T12:00:00Z`).toLocaleDateString("es-CO", { day: "numeric", month: "short", year: "numeric", timeZone: "UTC" });
                  return (
                    <li key={a.movement_id} className="px-5 py-3">
                      <div className="flex items-start justify-between gap-3">
                        <button type="button" onClick={() => setOpen(a.product.id)} className="min-w-0 text-left">
                          <span className="block truncate text-theme-sm font-medium text-gray-800 hover:underline dark:text-white/90">{a.product.name}</span>
                          <span className={cn("text-theme-xs font-medium", a.expired ? "text-error-600" : "text-warning-700")}>
                            {a.expired ? "Venció" : "Vence"} el {date}
                            {a.lot_number ? ` · lote ${a.lot_number}` : ""}
                          </span>
                          <span className="block text-theme-xs text-gray-500">
                            Entraron {qty(a.entered_quantity)} · hay {qty(a.stock)} en total
                          </span>
                        </button>
                      </div>
                      {canManage && (
                        <div className="mt-2 flex flex-wrap gap-2">
                          {canAdjust && (
                            <Button
                              size="sm"
                              variant="outline"
                              onClick={() =>
                                setMoving({
                                  mode: "adjust",
                                  product: a.product,
                                  stock: a.stock,
                                  lot: { movementId: a.movement_id, label: `lote ${a.lot_number ?? "sin número"}, vence ${date}`, quantity: Math.min(a.entered_quantity, a.stock) },
                                })
                              }
                            >
                              Dar de baja
                            </Button>
                          )}
                          <Button size="sm" variant="ghost" onClick={() => closeLot.mutate(a.movement_id)} disabled={closeLot.isPending}>
                            Ya lo revisé
                          </Button>
                        </div>
                      )}
                    </li>
                  );
                })}
              </ul>
            )}
          </Card>
        </div>
      )}

      {open && <ProductPanel productId={open} branchId={branchId} onClose={() => setOpen(null)} onEdit={(p) => setEditing(p)} onMove={startMove} />}
      {editing && (
        <ProductFormPanel
          product={editing === "new" ? null : editing}
          branchId={branchId}
          branchName={branchName}
          showCosts={showCosts || canAdjust}
          onClose={() => setEditing(null)}
          onSaved={(saved) => {
            setEditing(null);
            setOpen(saved.id);
          }}
        />
      )}
      {moving && (
        <MovementModal
          mode={moving.mode}
          product={moving.product}
          branches={branches}
          branchId={branchId}
          stock={moving.stock}
          showCosts={canAdjust}
          lot={moving.lot}
          onClose={() => setMoving(null)}
        />
      )}
      {importing && <ImportModal branchId={branchId} branchName={branchName} onClose={() => setImporting(false)} />}
    </>
  );
}
