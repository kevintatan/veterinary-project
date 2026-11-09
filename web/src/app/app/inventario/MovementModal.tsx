"use client";

import { Field, Input, Select, Textarea } from "@/components/form/fields";
import Button from "@/components/ui/button/Button";
import { Modal } from "@/components/ui/modal";
import { ApiError, api } from "@/lib/api";
import { inventoryKeys, qty, REASONS } from "@/lib/inventory";
import { useMutation, useQueryClient } from "@tanstack/react-query";
import { useState } from "react";

export type MovementMode = "purchase" | "adjust" | "transfer";

const TITLE: Record<MovementMode, string> = {
  purchase: "Registrar entrada",
  adjust: "Ajustar existencias",
  transfer: "Trasladar a otra sede",
};

/**
 * Purchase, adjustment or transfer of one product (docs/inventario.md).
 * For an expired lot, it opens as an adjustment "Vencido" linked to the lot.
 */
export default function MovementModal({
  mode,
  product,
  branches,
  branchId,
  stock,
  showCosts,
  lot,
  onClose,
}: {
  mode: MovementMode;
  product: { id: string; name: string; unit: { label: string } | string };
  branches: { id: string; name: string }[];
  branchId: string;
  stock: number;
  showCosts: boolean;
  lot?: { movementId: string; label: string; quantity: number };
  onClose: () => void;
}) {
  const queryClient = useQueryClient();
  const unit = typeof product.unit === "string" ? product.unit : product.unit.label;
  const [form, setForm] = useState({
    branch_id: branchId,
    to_branch_id: branches.find((b) => b.id !== branchId)?.id ?? "",
    quantity: lot ? String(lot.quantity) : "",
    reason: lot ? "expired" : "count",
    unit_cost: "",
    lot_number: "",
    expires_on: "",
    note: "",
  });
  const set = <K extends keyof typeof form>(key: K, value: string) => setForm((f) => ({ ...f, [key]: value }));

  const save = useMutation({
    mutationFn: () => {
      const base = { branch_id: form.branch_id, quantity: form.quantity === "" ? null : Number(form.quantity), note: form.note || null };
      const body =
        mode === "purchase"
          ? { ...base, unit_cost: form.unit_cost === "" ? null : Number(form.unit_cost), lot_number: form.lot_number || null, expires_on: form.expires_on || null }
          : mode === "adjust"
            ? { ...base, reason: form.reason, lot_movement_id: lot?.movementId ?? null }
            : { ...base, to_branch_id: form.to_branch_id };
      return api(`products/${product.id}/${mode}`, { method: "POST", body });
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: inventoryKeys.all });
      onClose();
    },
  });
  const err = (field: string) => (save.error instanceof ApiError ? save.error.errors[field]?.[0] : undefined);

  const isCount = mode === "adjust" && form.reason === "count";
  const after = (() => {
    const n = Number(form.quantity);
    if (form.quantity === "" || Number.isNaN(n)) return null;
    if (mode === "purchase") return stock + n;
    if (mode === "transfer") return stock - n;
    return isCount ? n : stock - n;
  })();

  return (
    <Modal isOpen onClose={onClose} className="m-4 w-full max-w-md rounded-2xl bg-white p-6 dark:bg-gray-900">
      <h2 className="pr-12 text-lg font-semibold text-gray-800 dark:text-white/90">{TITLE[mode]}</h2>
      <p className="mt-1 pr-12 text-theme-sm text-gray-500">
        {product.name}
        {lot ? ` · ${lot.label}` : ""}
      </p>

      <div className="mt-5 space-y-4">
        {branches.length > 1 && (
          <div className={mode === "transfer" ? "grid grid-cols-2 gap-4" : ""}>
            <Field label={mode === "transfer" ? "Desde" : "Sede"} htmlFor="m-branch">
              <Select id="m-branch" value={form.branch_id} onChange={(e) => set("branch_id", e.target.value)} disabled={!!lot}>
                {branches.map((b) => (
                  <option key={b.id} value={b.id}>
                    {b.name}
                  </option>
                ))}
              </Select>
            </Field>
            {mode === "transfer" && (
              <Field label="Hacia" htmlFor="m-to" error={err("to_branch_id")}>
                <Select id="m-to" value={form.to_branch_id} onChange={(e) => set("to_branch_id", e.target.value)}>
                  {branches.map((b) => (
                    <option key={b.id} value={b.id}>
                      {b.name}
                    </option>
                  ))}
                </Select>
              </Field>
            )}
          </div>
        )}

        {mode === "adjust" && (
          <Field label="Motivo" htmlFor="m-reason">
            <Select id="m-reason" value={form.reason} onChange={(e) => set("reason", e.target.value)} disabled={!!lot}>
              {REASONS.map(([v, l]) => (
                <option key={v} value={v}>
                  {l}
                </option>
              ))}
            </Select>
          </Field>
        )}

        <Field
          label={isCount ? `Cantidad contada (${unit})` : mode === "adjust" ? `Cantidad a retirar (${unit})` : `Cantidad (${unit})`}
          htmlFor="m-qty"
          hint={after !== null ? `Existencia actual: ${qty(stock)} → quedará en ${qty(after)}${after < 0 ? " (en negativo)" : ""}.` : `Existencia actual: ${qty(stock)}.`}
          error={err("quantity")}
        >
          <Input id="m-qty" type="number" min={0} step="any" inputMode="decimal" value={form.quantity} onChange={(e) => set("quantity", e.target.value)} invalid={!!err("quantity")} autoFocus />
        </Field>

        {mode === "purchase" && (
          <>
            {showCosts && (
              <Field label="Costo unitario" htmlFor="m-cost" optional hint="Actualiza el costo del producto." error={err("unit_cost")}>
                <Input id="m-cost" type="number" min={0} step="any" inputMode="decimal" value={form.unit_cost} onChange={(e) => set("unit_cost", e.target.value)} />
              </Field>
            )}
            <div className="grid grid-cols-2 gap-4">
              <Field label="Lote" htmlFor="m-lot" optional>
                <Input id="m-lot" value={form.lot_number} onChange={(e) => set("lot_number", e.target.value)} />
              </Field>
              <Field label="Vence" htmlFor="m-exp" optional hint="Avisa 60 días antes.">
                <Input id="m-exp" type="date" value={form.expires_on} onChange={(e) => set("expires_on", e.target.value)} />
              </Field>
            </div>
          </>
        )}

        <Field label="Nota" htmlFor="m-note" optional={!(mode === "adjust" && form.reason === "other")} error={err("note")}>
          <Textarea id="m-note" rows={2} maxLength={255} value={form.note} onChange={(e) => set("note", e.target.value)} placeholder={mode === "purchase" ? "Proveedor, factura de compra…" : ""} />
        </Field>

        {save.error && !(save.error instanceof ApiError && Object.keys(save.error.errors).length) && <p className="text-theme-sm text-error-600">{save.error.message}</p>}
      </div>

      <div className="mt-6 flex justify-end gap-3">
        <Button variant="outline" onClick={onClose}>
          Cancelar
        </Button>
        <Button onClick={() => save.mutate()} loading={save.isPending} disabled={form.quantity === ""}>
          {mode === "purchase" ? "Registrar entrada" : mode === "adjust" ? "Guardar ajuste" : "Trasladar"}
        </Button>
      </div>
    </Modal>
  );
}
