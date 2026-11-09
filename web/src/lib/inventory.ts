/** Inventory (docs/inventario.md): shapes returned by the API and small helpers. */

import type { Labelled } from "@/lib/types";

/** "none": this branch has never had the product (not "agotado", just not carried there). */
export type StockStatus = "ok" | "low" | "out" | "negative" | "untracked" | "none";

export type ProductRow = {
  id: string;
  name: string;
  sku: string | null;
  kind: Labelled<"product" | "medication" | "vaccine" | "supply">;
  unit: Labelled;
  sale_price: number;
  cost_price: number | null; // only for who can see costs
  tax_rate: number;
  tracks_stock: boolean;
  min_stock: number;
  is_active: boolean;
  stock: number;
  stock_total: number;
  stock_status: StockStatus;
};

export type Movement = {
  id: string;
  type: Labelled;
  reason: Labelled | null;
  quantity: number;
  balance_after: number;
  unit_cost: number | null;
  lot_number: string | null;
  expires_on: string | null;
  note: string | null;
  branch: { id: string; name: string } | null;
  user: { id: string; name: string } | null;
  occurred_at: string;
};

export type ProductDetail = ProductRow & {
  stocks: { branch: { id: string; name: string }; quantity: number; handled: boolean }[];
  movements: Movement[];
};

export type Alerts = {
  low: { product: { id: string; name: string; unit: string; kind: string }; quantity: number; min_stock: number; status: StockStatus }[];
  expiring: { movement_id: string; product: { id: string; name: string; unit: string }; lot_number: string | null; expires_on: string; expired: boolean; entered_quantity: number; stock: number }[];
};

export const KINDS: [ProductRow["kind"]["value"], string][] = [
  ["product", "Producto"],
  ["medication", "Medicamento"],
  ["vaccine", "Vacuna"],
  ["supply", "Insumo"],
];

export const UNITS: [string, string][] = [
  ["unit", "Unidad"],
  ["box", "Caja"],
  ["ml", "ml"],
  ["tablet", "Tableta"],
  ["dose", "Dosis"],
  ["g", "g"],
  ["kg", "kg"],
  ["bottle", "Frasco"],
  ["pipette", "Pipeta"],
];

export const REASONS: [string, string][] = [
  ["count", "Conteo físico"],
  ["damage", "Daño"],
  ["loss", "Pérdida"],
  ["expired", "Vencido"],
  ["other", "Otro"],
];

export const STOCK_STATUS: Record<StockStatus, { label: string; tone: string }> = {
  ok: { label: "", tone: "text-gray-800 dark:text-white/90" },
  low: { label: "Bajo", tone: "text-warning-700 dark:text-warning-400" },
  out: { label: "Agotado", tone: "text-error-600 dark:text-error-400" },
  negative: { label: "En negativo", tone: "text-error-700 dark:text-error-400" },
  untracked: { label: "Sin control", tone: "text-gray-400" },
  none: { label: "No se maneja aquí", tone: "text-gray-400" },
};

export const inventoryKeys = {
  all: ["inventory"] as const,
  products: (branchId: string, filters: object) => ["inventory", "products", branchId, filters] as const,
  product: (id: string) => ["inventory", "product", id] as const,
  alerts: (branchId: string) => ["inventory", "alerts", branchId] as const,
};

/** 12 → "12", 2.5 → "2,5" (up to 3 decimals, Colombian format). */
export function qty(value: number): string {
  return new Intl.NumberFormat("es-CO", { maximumFractionDigits: 3 }).format(value);
}

/** Singular / plural of each unit, as people say it: "1 tableta", "6 tabletas", "5 ml". */
const UNIT_WORDS: Record<string, [string, string]> = {
  unit: ["unidad", "unidades"],
  box: ["caja", "cajas"],
  ml: ["ml", "ml"],
  tablet: ["tableta", "tabletas"],
  dose: ["dosis", "dosis"],
  g: ["g", "g"],
  kg: ["kg", "kg"],
  bottle: ["frasco", "frascos"],
  pipette: ["pipeta", "pipetas"],
};

/** 6 + "tablet" → "6 tabletas"; -1 + "unit" → "-1 unidad". */
export function qtyUnit(value: number, unit: string): string {
  const [one, many] = UNIT_WORDS[unit] ?? [unit, unit];
  return `${qty(value)} ${Math.abs(value) === 1 ? one : many}`;
}
