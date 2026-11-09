"use client";

import SidePanel from "@/components/common/SidePanel";
import { Checkbox, Field, Input, Select } from "@/components/form/fields";
import Button from "@/components/ui/button/Button";
import { ApiError, api } from "@/lib/api";
import { inventoryKeys, KINDS, type ProductDetail, type ProductRow, UNITS } from "@/lib/inventory";
import { useMutation, useQueryClient } from "@tanstack/react-query";
import { useState } from "react";

/**
 * Create or edit a product. Prices include IVA (what the owner pays).
 * Costs only for who can see them; initial stock only when creating.
 */
export default function ProductFormPanel({
  product,
  branchId,
  branchName,
  showCosts,
  onClose,
  onSaved,
}: {
  product: ProductRow | null;
  branchId: string;
  branchName: string;
  showCosts: boolean;
  onClose: () => void;
  onSaved: (p: ProductDetail) => void;
}) {
  const queryClient = useQueryClient();
  const creating = product === null;
  const [form, setForm] = useState({
    name: product?.name ?? "",
    sku: product?.sku ?? "",
    kind: product?.kind.value ?? "product",
    unit: product?.unit.value ?? "unit",
    sale_price: product ? String(product.sale_price) : "",
    cost_price: product?.cost_price != null ? String(product.cost_price) : "",
    tax_rate: product ? String(product.tax_rate) : "0",
    tracks_stock: product?.tracks_stock ?? true,
    min_stock: product ? String(product.min_stock) : "",
    is_active: product?.is_active ?? true,
    initial_quantity: "",
    lot_number: "",
    expires_on: "",
  });
  const set = <K extends keyof typeof form>(key: K, value: (typeof form)[K]) => setForm((f) => ({ ...f, [key]: value }));

  const save = useMutation({
    mutationFn: () => {
      const body = {
        name: form.name,
        sku: form.sku || null,
        kind: form.kind,
        unit: form.unit,
        sale_price: form.sale_price === "" ? null : Number(form.sale_price),
        cost_price: showCosts && form.cost_price !== "" ? Number(form.cost_price) : null,
        tax_rate: Number(form.tax_rate),
        tracks_stock: form.tracks_stock,
        min_stock: form.min_stock === "" ? 0 : Number(form.min_stock),
        is_active: form.is_active,
        ...(creating && form.tracks_stock && form.initial_quantity !== ""
          ? { initial_quantity: Number(form.initial_quantity), branch_id: branchId, lot_number: form.lot_number || null, expires_on: form.expires_on || null }
          : {}),
      };
      return api<{ data: ProductDetail }>(creating ? "products" : `products/${product.id}`, { method: creating ? "POST" : "PATCH", body }).then((r) => r.data);
    },
    onSuccess: (saved) => {
      queryClient.invalidateQueries({ queryKey: inventoryKeys.all });
      onSaved(saved);
    },
  });
  const err = (field: string) => (save.error instanceof ApiError ? save.error.errors[field]?.[0] : undefined);

  return (
    <SidePanel
      title={creating ? "Nuevo producto" : "Editar producto"}
      description="El precio de venta es el que paga el tutor, con IVA incluido."
      onClose={onClose}
      footer={
        <div className="flex justify-end gap-3">
          <Button variant="outline" onClick={onClose}>
            Cancelar
          </Button>
          <Button onClick={() => save.mutate()} loading={save.isPending} disabled={!form.name.trim()}>
            {creating ? "Crear producto" : "Guardar"}
          </Button>
        </div>
      }
    >
      <div className="space-y-4">
        <Field label="Nombre" htmlFor="p-name" error={err("name")}>
          <Input id="p-name" value={form.name} onChange={(e) => set("name", e.target.value)} placeholder="Ej.: NexGard 10-25 kg" invalid={!!err("name")} autoFocus />
        </Field>
        <div className="grid grid-cols-2 gap-4">
          <Field label="Tipo" htmlFor="p-kind">
            <Select id="p-kind" value={form.kind} onChange={(e) => set("kind", e.target.value as typeof form.kind)}>
              {KINDS.map(([v, l]) => (
                <option key={v} value={v}>
                  {l}
                </option>
              ))}
            </Select>
          </Field>
          <Field label="Unidad" htmlFor="p-unit" hint="Cómo se cuenta y se vende.">
            <Select id="p-unit" value={form.unit} onChange={(e) => set("unit", e.target.value)}>
              {UNITS.map(([v, l]) => (
                <option key={v} value={v}>
                  {l}
                </option>
              ))}
            </Select>
          </Field>
        </div>
        <div className="grid grid-cols-2 gap-4">
          <Field label="Precio de venta" htmlFor="p-price" error={err("sale_price")}>
            <Input id="p-price" type="number" min={0} step="any" inputMode="decimal" value={form.sale_price} onChange={(e) => set("sale_price", e.target.value)} invalid={!!err("sale_price")} />
          </Field>
          <Field label="IVA incluido" htmlFor="p-tax" hint="Validar con el contador.">
            <Select id="p-tax" value={form.tax_rate} onChange={(e) => set("tax_rate", e.target.value)}>
              <option value="0">0 % (excluido o exento)</option>
              <option value="5">5 %</option>
              <option value="19">19 %</option>
            </Select>
          </Field>
        </div>
        <div className="grid grid-cols-2 gap-4">
          {showCosts && (
            <Field label="Costo" htmlFor="p-cost" optional hint="Se actualiza con cada entrada." error={err("cost_price")}>
              <Input id="p-cost" type="number" min={0} step="any" inputMode="decimal" value={form.cost_price} onChange={(e) => set("cost_price", e.target.value)} />
            </Field>
          )}
          <Field label="Código" htmlFor="p-sku" optional hint="Código de barras o referencia." error={err("sku")}>
            <Input id="p-sku" value={form.sku} onChange={(e) => set("sku", e.target.value)} invalid={!!err("sku")} />
          </Field>
        </div>

        <div className="rounded-xl border border-gray-200 p-4 dark:border-gray-800">
          <Checkbox
            checked={form.tracks_stock}
            onChange={(e) => set("tracks_stock", e.target.checked)}
            label="Controlar existencias"
            description="Desactívalo para lo que no se cuenta (por ejemplo, un baño medicado que se cobra como producto)."
          />
          {err("tracks_stock") && <p className="mt-1 text-theme-xs text-error-600">{err("tracks_stock")}</p>}
          {form.tracks_stock && (
            <div className="mt-4 space-y-4">
              <Field label="Existencia mínima" htmlFor="p-min" optional hint="Por debajo de esto aparece en alertas.">
                <Input id="p-min" type="number" min={0} step="any" value={form.min_stock} onChange={(e) => set("min_stock", e.target.value)} />
              </Field>
              {creating && (
                <>
                  <Field label={`Existencia inicial en ${branchName}`} htmlFor="p-initial" optional error={err("initial_quantity")}>
                    <Input id="p-initial" type="number" min={0} step="any" value={form.initial_quantity} onChange={(e) => set("initial_quantity", e.target.value)} />
                  </Field>
                  {form.initial_quantity !== "" && (
                    <div className="grid grid-cols-2 gap-4">
                      <Field label="Lote" htmlFor="p-lot" optional>
                        <Input id="p-lot" value={form.lot_number} onChange={(e) => set("lot_number", e.target.value)} />
                      </Field>
                      <Field label="Vence" htmlFor="p-exp" optional>
                        <Input id="p-exp" type="date" value={form.expires_on} onChange={(e) => set("expires_on", e.target.value)} />
                      </Field>
                    </div>
                  )}
                </>
              )}
            </div>
          )}
        </div>

        {!creating && (
          <Checkbox checked={form.is_active} onChange={(e) => set("is_active", e.target.checked)} label="Activo" description="Los inactivos no aparecen para vender; su historial se conserva." />
        )}
        {save.error && !(save.error instanceof ApiError && Object.keys(save.error.errors).length) && <p className="text-theme-sm text-error-600">{save.error.message}</p>}
      </div>
    </SidePanel>
  );
}
