/** Sales and payments (docs/inventario.md, V2): shapes returned by the API. */

import type { Labelled } from "@/lib/types";

export type SaleStatus = "open" | "partially_paid" | "paid" | "void";

export type SaleSummary = {
  id: string;
  number: number;
  status: Labelled<SaleStatus>;
  customer: { name: string; document: string | null; phone: string | null } | null;
  owner_id: string | null;
  total: number;
  paid_total: number;
  balance: number;
  sold_at: string;
  seller: string | null;
  branch: { id: string; name: string } | null;
};

export type SaleItem = {
  id: string;
  type: "product" | "service" | "custom";
  description: string;
  unit: string | null;
  quantity: number;
  unit_price: number;
  discount: number;
  tax_rate: number;
  tax_amount: number;
  total: number;
  moves_stock: boolean;
  /** V3: where it came from (the appointment charged, or the vaccine / deworming / procedure). */
  origin: { kind: "appointment" | "clinical_record"; label: string; pet_id: string | null } | null;
};

export type SalePayment = {
  id: string;
  method: Labelled;
  amount: number;
  reference: string | null;
  received_by: string | null;
  paid_at: string;
  voided: boolean;
  void_reason: string | null;
};

export type SaleDetail = SaleSummary & {
  subtotal: number;
  discount_total: number;
  tax_total: number;
  notes: string | null;
  owner: { id: string; full_name: string; mobile_phone: string | null } | null;
  void_reason: string | null;
  voided_by: string | null;
  voided_at: string | null;
  editable: boolean;
  items: SaleItem[];
  payments: SalePayment[];
};

export type Sellable = { type: "product" | "service"; id: string; name: string; detail: string; price: number; unit: string | null; stock: number | null };

export const PAYMENT_METHODS: [string, string][] = [
  ["cash", "Efectivo"],
  ["card", "Tarjeta"],
  ["transfer", "Transferencia"],
  ["nequi", "Nequi"],
  ["daviplata", "Daviplata"],
  ["other", "Otro"],
];

export const SALE_BADGE: Record<SaleStatus, string> = {
  open: "bg-warning-50 text-warning-700 dark:bg-warning-500/15 dark:text-warning-400",
  partially_paid: "bg-blue-light-50 text-blue-light-700 dark:bg-blue-light-500/15 dark:text-blue-light-400",
  paid: "bg-success-50 text-success-700 dark:bg-success-500/15 dark:text-success-400",
  void: "bg-gray-100 text-gray-500 line-through dark:bg-white/5",
};

export const salesKeys = {
  all: ["sales"] as const,
  list: (filters: object) => ["sales", "list", filters] as const,
  sale: (id: string) => ["sales", "sale", id] as const,
  account: (ownerId: string) => ["sales", "account", ownerId] as const,
};

/** 12 → "000012", like the printed receipt. */
export const saleNumber = (n: number) => `N.º ${String(n).padStart(6, "0")}`;
