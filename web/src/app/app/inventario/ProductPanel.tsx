"use client";

import SidePanel from "@/components/common/SidePanel";
import { useCan, useSession } from "@/components/providers/SessionProvider";
import Button from "@/components/ui/button/Button";
import { api } from "@/lib/api";
import { inventoryKeys, type ProductDetail, qty, qtyUnit, STOCK_STATUS } from "@/lib/inventory";
import { formatCop } from "@/lib/settings";
import { cn } from "@/utils";
import { useQuery } from "@tanstack/react-query";
import type { MovementMode } from "./MovementModal";

/** One product: stock per branch, actions and its full movement history. */
export default function ProductPanel({
  productId,
  branchId,
  onClose,
  onEdit,
  onMove,
}: {
  productId: string;
  branchId: string;
  onClose: () => void;
  onEdit: (p: ProductDetail) => void;
  onMove: (mode: MovementMode, p: ProductDetail) => void;
}) {
  const { clinic } = useSession();
  const canManage = useCan("inventory.manage");
  const canAdjust = useCan("inventory.adjust");
  const detail = useQuery({ queryKey: inventoryKeys.product(productId), queryFn: () => api<{ data: ProductDetail }>(`products/${productId}`).then((r) => r.data) });
  const p = detail.data;
  const here = p?.stocks.find((s) => s.branch.id === branchId)?.quantity ?? 0;
  const when = (iso: string) => new Date(iso).toLocaleString("es-CO", { day: "numeric", month: "short", year: "numeric", hour: "numeric", minute: "2-digit", timeZone: clinic.timezone });

  return (
    <SidePanel
      title={p?.name ?? "Producto"}
      description={p ? [p.kind.label, p.sku, p.is_active ? null : "Inactivo"].filter(Boolean).join(" · ") : undefined}
      onClose={onClose}
      width="max-w-xl"
      footer={
        p && canManage ? (
          <div className="flex flex-wrap justify-end gap-2">
            <Button variant="outline" size="sm" onClick={() => onEdit(p)}>
              Editar
            </Button>
            {p.tracks_stock && (
              <>
                {p.stocks.length > 1 && (
                  <Button variant="outline" size="sm" onClick={() => onMove("transfer", p)}>
                    Trasladar
                  </Button>
                )}
                {canAdjust && (
                  <Button variant="outline" size="sm" onClick={() => onMove("adjust", p)}>
                    Ajustar
                  </Button>
                )}
                <Button size="sm" onClick={() => onMove("purchase", p)}>
                  Registrar entrada
                </Button>
              </>
            )}
          </div>
        ) : undefined
      }
    >
      {!p ? (
        <div className="space-y-3">
          {[1, 2, 3].map((i) => (
            <div key={i} className="h-14 animate-pulse rounded-xl bg-gray-100 dark:bg-white/5" />
          ))}
        </div>
      ) : (
        <div className="space-y-6">
          <div className="grid grid-cols-3 gap-3">
            <Stat label="Precio" value={formatCop(p.sale_price)} sub={p.tax_rate ? `IVA ${p.tax_rate} % incluido` : "Sin IVA"} />
            <Stat label="Costo" value={p.cost_price !== null ? formatCop(p.cost_price) : "—"} sub={p.cost_price !== null && p.sale_price ? `Margen ${Math.round(((p.sale_price - p.cost_price) / p.sale_price) * 100)} %` : canAdjust ? "Sin registrar" : "Solo administrador"} />
            <Stat label="Mínimo" value={p.tracks_stock ? qtyUnit(p.min_stock, p.unit.value) : "—"} />
          </div>

          {p.tracks_stock ? (
            <section>
              <h3 className="mb-2 text-theme-xs font-semibold tracking-wide text-gray-400 uppercase">Existencias</h3>
              <ul className="divide-y divide-gray-100 rounded-xl border border-gray-200 dark:divide-gray-800 dark:border-gray-800">
                {p.stocks.map((s) => {
                  const status = !s.handled ? "none" : s.quantity < 0 ? "negative" : s.quantity === 0 ? "out" : p.min_stock > 0 && s.quantity <= p.min_stock ? "low" : "ok";
                  return (
                    <li key={s.branch.id} className={cn("flex items-center justify-between px-4 py-2.5 text-theme-sm", s.branch.id === branchId && "bg-brand-25 dark:bg-brand-500/5")}>
                      <span className="text-gray-700 dark:text-gray-300">{s.branch.name}</span>
                      <span className={cn("font-semibold tabular-nums", STOCK_STATUS[status].tone)}>
                        {qtyUnit(s.quantity, p.unit.value)}
                        {STOCK_STATUS[status].label && <span className="ml-2 text-theme-xs font-medium">{STOCK_STATUS[status].label}</span>}
                      </span>
                    </li>
                  );
                })}
              </ul>
              {here < 0 && <p className="mt-2 text-theme-xs text-error-600">Hay más salidas que entradas registradas en esta sede: registra la entrada que falta o haz un conteo.</p>}
            </section>
          ) : (
            <p className="rounded-xl bg-gray-50 px-4 py-3 text-theme-sm text-gray-600 dark:bg-white/5 dark:text-gray-300">Este producto no controla existencias.</p>
          )}

          {p.tracks_stock && (
            <section>
              <h3 className="mb-2 text-theme-xs font-semibold tracking-wide text-gray-400 uppercase">Movimientos</h3>
              {p.movements.length === 0 ? (
                <p className="text-theme-sm text-gray-500">Sin movimientos todavía.</p>
              ) : (
                <ul className="space-y-2">
                  {p.movements.map((m) => (
                    <li key={m.id} className="rounded-xl border border-gray-100 px-4 py-3 dark:border-gray-800">
                      <div className="flex items-start justify-between gap-3">
                        <div className="min-w-0">
                          <p className="text-theme-sm font-medium text-gray-800 dark:text-white/90">
                            {m.type.label}
                            {m.reason ? ` · ${m.reason.label}` : ""}
                          </p>
                          <p className="text-theme-xs text-gray-500">
                            {when(m.occurred_at)}
                            {m.user ? ` · ${m.user.name}` : ""}
                            {p.stocks.length > 1 && m.branch ? ` · ${m.branch.name}` : ""}
                          </p>
                        </div>
                        <div className="text-right">
                          <p className={cn("text-theme-sm font-semibold tabular-nums", m.quantity > 0 ? "text-success-600" : "text-error-600")}>
                            {m.quantity > 0 ? "+" : ""}
                            {qty(m.quantity)}
                          </p>
                          <p className="text-theme-xs text-gray-400 tabular-nums">Queda {qty(m.balance_after)}</p>
                        </div>
                      </div>
                      {(m.lot_number || m.expires_on || m.unit_cost !== null || m.note) && (
                        <p className="mt-1.5 text-theme-xs text-gray-500">
                          {[m.lot_number && `Lote ${m.lot_number}`, m.expires_on && `vence ${new Date(`${m.expires_on}T12:00:00Z`).toLocaleDateString("es-CO", { day: "numeric", month: "short", year: "numeric", timeZone: "UTC" })}`, m.unit_cost !== null && `costo ${formatCop(m.unit_cost)}`, m.note]
                            .filter(Boolean)
                            .join(" · ")}
                        </p>
                      )}
                    </li>
                  ))}
                </ul>
              )}
            </section>
          )}
        </div>
      )}
    </SidePanel>
  );
}

function Stat({ label, value, sub }: { label: string; value: string; sub?: string }) {
  return (
    <div className="rounded-xl border border-gray-200 px-3 py-2.5 dark:border-gray-800">
      <p className="text-theme-xs text-gray-500">{label}</p>
      <p className="truncate text-base font-semibold text-gray-800 tabular-nums dark:text-white/90">{value}</p>
      {sub && <p className="truncate text-[11px] text-gray-400">{sub}</p>}
    </div>
  );
}
