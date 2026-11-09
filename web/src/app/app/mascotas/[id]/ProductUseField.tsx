"use client";

import { Input } from "@/components/form/fields";
import { api } from "@/lib/api";
import { type ProductDetail, type ProductRow, qtyUnit } from "@/lib/inventory";
import { formatCop } from "@/lib/settings";
import { cn } from "@/utils";
import { useQuery } from "@tanstack/react-query";
import { useDeferredValue, useState } from "react";

export type ProductUse = { product: Pick<ProductRow, "id" | "name" | "unit" | "sale_price" | "stock" | "tracks_stock">; quantity: string; charge: boolean };

/**
 * The inventory product used in a vaccine, deworming or procedure (docs/inventario.md, V3).
 * Charged to the owner's account of the day, or only taken out of stock if included.
 * onPicked receives the product's latest lot (number and expiry) to prefill the record.
 */
export default function ProductUseField({
  branchId,
  kind,
  value,
  onChange,
  onPicked,
}: {
  branchId: string;
  kind?: "vaccine" | "medication";
  value: ProductUse | null;
  onChange: (v: ProductUse | null) => void;
  onPicked?: (product: ProductRow, lot: { number: string; expires_on: string | null } | null) => void;
}) {
  const [q, setQ] = useState("");
  const term = useDeferredValue(q.trim());

  const results = useQuery({
    queryKey: ["inventory", "pick", branchId, kind, term],
    queryFn: () => {
      const query = new URLSearchParams({ branch_id: branchId, ...(term ? { q: term } : {}), ...(kind ? { kind } : {}) });
      return api<{ data: ProductRow[] }>(`products?${query}`).then((r) => r.data);
    },
    enabled: !value && !!branchId,
  });

  const pick = async (p: ProductRow) => {
    onChange({ product: p, quantity: "1", charge: true });
    setQ("");
    if (!onPicked) return;
    // Latest lot that came in (purchase or initial stock), to prefill lot and expiry.
    const detail = await api<{ data: ProductDetail }>(`products/${p.id}`).then((r) => r.data).catch(() => null);
    const lot = detail?.movements.find((m) => ["purchase", "initial"].includes(m.type.value) && m.lot_number);
    onPicked(p, lot ? { number: lot.lot_number!, expires_on: lot.expires_on } : null);
  };

  return (
    <div className="rounded-xl border border-dashed border-gray-300 p-4 dark:border-gray-700">
      <p className="text-theme-sm font-medium text-gray-800 dark:text-white/90">
        Producto del inventario <span className="font-normal text-gray-400">(opcional)</span>
      </p>
      <p className="mb-3 text-theme-xs text-gray-500">Se descuenta de las existencias y se carga a la cuenta del tutor de hoy.</p>

      {!value ? (
        <>
          <Input value={q} onChange={(e) => setQ(e.target.value)} placeholder={kind === "vaccine" ? "Buscar vacuna en inventario…" : "Buscar producto en inventario…"} aria-label="Buscar producto del inventario" />
          {results.data && results.data.length > 0 && (
            <ul className="mt-2 max-h-48 divide-y divide-gray-100 overflow-y-auto rounded-lg border border-gray-200 dark:divide-gray-800 dark:border-gray-700">
              {results.data.slice(0, 8).map((p) => (
                <li key={p.id}>
                  <button type="button" onClick={() => pick(p)} className="flex w-full items-center justify-between gap-3 px-3 py-2 text-left hover:bg-gray-50 dark:hover:bg-white/5">
                    <span className="min-w-0 truncate text-theme-sm text-gray-800 dark:text-white/90">{p.name}</span>
                    <span className={cn("shrink-0 text-theme-xs", p.stock_status === "out" || p.stock_status === "negative" ? "text-error-600" : "text-gray-500")}>
                      {p.tracks_stock && p.stock_status !== "none" ? `hay ${qtyUnit(p.stock, p.unit.value)}` : ""} · {formatCop(p.sale_price)}
                    </span>
                  </button>
                </li>
              ))}
            </ul>
          )}
          {results.data && results.data.length === 0 && term && <p className="mt-2 text-theme-xs text-gray-500">No hay productos con ese nombre en el inventario.</p>}
        </>
      ) : (
        <div className="space-y-3">
          <div className="flex flex-wrap items-center gap-3">
            <span className="min-w-0 flex-1 truncate text-theme-sm font-medium text-gray-800 dark:text-white/90">
              {value.product.name} <span className="font-normal text-gray-500">· {formatCop(value.product.sale_price)}</span>
            </span>
            <label className="flex items-center gap-2 text-theme-xs text-gray-500">
              Cantidad
              <input
                type="number"
                min={0}
                step="any"
                value={value.quantity}
                onChange={(e) => onChange({ ...value, quantity: e.target.value })}
                className="h-9 w-20 rounded-lg border border-gray-300 px-2 text-right text-theme-sm dark:border-gray-700 dark:bg-gray-900"
              />
            </label>
            <button type="button" onClick={() => onChange(null)} className="text-theme-xs font-medium text-brand-600 hover:underline">
              Quitar
            </button>
          </div>
          <label className="flex items-start gap-2 text-theme-sm text-gray-700 dark:text-gray-300">
            <input type="checkbox" checked={value.charge} onChange={(e) => onChange({ ...value, charge: e.target.checked })} className="mt-0.5 accent-brand-500" />
            <span>
              Cargar a la cuenta del tutor
              <span className="block text-theme-xs text-gray-500">{value.charge ? "Se suma a la venta de hoy (la misma de la consulta, si ya existe)." : "Incluido o de cortesía: solo se descuenta del inventario."}</span>
            </span>
          </label>
        </div>
      )}
    </div>
  );
}
