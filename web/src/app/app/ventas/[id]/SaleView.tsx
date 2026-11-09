"use client";

import { Card, Skeleton } from "@/components/common/Card";
import OwnerPetPicker from "@/components/domain/OwnerPetPicker";
import { Field, Input, Select, Textarea } from "@/components/form/fields";
import { useCan, useSession } from "@/components/providers/SessionProvider";
import Button from "@/components/ui/button/Button";
import { Modal } from "@/components/ui/modal";
import { ApiError, api } from "@/lib/api";
import { qtyUnit } from "@/lib/inventory";
import { formatPhone } from "@/lib/patients";
import { PAYMENT_METHODS, SALE_BADGE, type SaleDetail, type SaleItem, salesKeys, saleNumber, type Sellable } from "@/lib/sales";
import { formatCop } from "@/lib/settings";
import { cn } from "@/utils";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import Link from "next/link";
import { useDeferredValue, useEffect, useRef, useState } from "react";

/**
 * One sale (docs/inventario.md, V2): add what was sold, take payments and
 * print the receipt (not an electronic invoice). Every change returns the
 * whole sale, so totals, stock and status always come from the API.
 */
export default function SaleView({ saleId }: { saleId: string }) {
  const { clinic } = useSession();
  const canSell = useCan("sales.manage");
  const canVoid = useCan("sales.void");
  const queryClient = useQueryClient();
  const key = salesKeys.sale(saleId);
  const sale = useQuery({ queryKey: key, queryFn: () => api<{ data: SaleDetail }>(`sales/${saleId}`).then((r) => r.data) });

  /** Any change: the API answers with the updated sale. */
  const change = useMutation({
    mutationFn: ({ path, method = "POST", body }: { path: string; method?: string; body?: unknown }) => api<{ data: SaleDetail }>(path, { method, body }).then((r) => r.data),
    onSuccess: (s) => {
      queryClient.setQueryData(key, s);
      queryClient.invalidateQueries({ queryKey: ["sales", "list"] });
      queryClient.invalidateQueries({ queryKey: ["inventory"] });
      if (s.owner_id) queryClient.invalidateQueries({ queryKey: salesKeys.account(s.owner_id) });
    },
  });
  const [voiding, setVoiding] = useState<{ kind: "sale" } | { kind: "payment"; id: string } | null>(null);
  const [changingCustomer, setChangingCustomer] = useState(false);

  const s = sale.data;
  if (!s) return <Skeleton className="h-96" />;

  const editable = s.editable && canSell;
  const when = (iso: string) => new Date(iso).toLocaleString("es-CO", { day: "numeric", month: "short", year: "numeric", hour: "numeric", minute: "2-digit", timeZone: clinic.timezone });
  const error = change.error instanceof ApiError ? (Object.values(change.error.errors)[0]?.[0] ?? change.error.message) : change.error?.message;

  return (
    <>
      <div className="mb-6 flex flex-wrap items-start justify-between gap-4">
        <div>
          <Link href="/app/ventas" className="text-theme-sm text-gray-500 hover:text-gray-800 dark:hover:text-white">
            ← Ventas
          </Link>
          <div className="mt-1 flex flex-wrap items-center gap-3">
            <h1 className="text-title-sm font-semibold text-gray-800 dark:text-white/90">Venta {saleNumber(s.number)}</h1>
            <span className={cn("rounded-full px-2.5 py-0.5 text-theme-xs font-medium", SALE_BADGE[s.status.value])}>{s.status.label}</span>
          </div>
          <p className="text-theme-sm text-gray-500">
            {when(s.sold_at)}
            {s.seller ? ` · ${s.seller}` : ""}
            {s.branch ? ` · ${s.branch.name}` : ""}
          </p>
        </div>
        <div className="flex flex-wrap gap-2">
          <a href={`/api/backend/sales/${s.id}/receipt`} target="_blank" rel="noreferrer" className="inline-flex items-center rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300">
            Comprobante
          </a>
          {canVoid && s.status.value !== "void" && (
            <button
              type="button"
              onClick={() => setVoiding({ kind: "sale" })}
              className="inline-flex items-center rounded-lg border border-error-200 px-4 py-2 text-sm font-medium text-error-600 hover:bg-error-50 dark:border-error-500/30 dark:hover:bg-error-500/10"
            >
              Anular venta
            </button>
          )}
        </div>
      </div>

      {s.status.value === "void" && (
        <div className="mb-4 rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 text-theme-sm text-gray-700 dark:border-gray-700 dark:bg-white/5 dark:text-gray-300">
          <b>Venta anulada</b>
          {s.voided_by ? ` por ${s.voided_by}` : ""}
          {s.voided_at ? ` el ${when(s.voided_at)}` : ""}: {s.void_reason}. Los productos volvieron al inventario.
          {s.paid_total > 0 && <> Tenía pagos por {formatCop(s.paid_total)}: si corresponde, devuélvelos al tutor.</>}
        </div>
      )}
      {error && <p className="mb-4 rounded-lg bg-error-50 px-4 py-2 text-theme-sm text-error-700 dark:bg-error-500/10">{error}</p>}

      <div className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
        <div className="space-y-6">
          {/* Customer */}
          <Card>
            <div className="flex flex-wrap items-center justify-between gap-3 px-5 py-4">
              {changingCustomer ? (
                <div className="w-full">
                  <OwnerPetPicker
                    owner={null}
                    petId=""
                    withPet={false}
                    autoFocus
                    onChange={(o) => {
                      if (o) change.mutate({ path: `sales/${s.id}`, method: "PATCH", body: { owner_id: o.id } }, { onSuccess: () => setChangingCustomer(false) });
                    }}
                  />
                  <div className="mt-2 flex gap-3 text-theme-sm">
                    <button type="button" onClick={() => change.mutate({ path: `sales/${s.id}`, method: "PATCH", body: { owner_id: null } }, { onSuccess: () => setChangingCustomer(false) })} className="text-brand-600 hover:underline">
                      Dejar como venta de mostrador
                    </button>
                    <button type="button" onClick={() => setChangingCustomer(false)} className="text-gray-500 hover:underline">
                      Cancelar
                    </button>
                  </div>
                </div>
              ) : (
                <>
                  <div>
                    <p className="text-theme-xs font-semibold tracking-wide text-gray-400 uppercase">Cliente</p>
                    {s.customer ? (
                      <p className="text-base font-medium text-gray-800 dark:text-white/90">
                        {s.owner ? (
                          <Link href={`/app/tutores/${s.owner.id}`} className="hover:underline">
                            {s.customer.name}
                          </Link>
                        ) : (
                          s.customer.name
                        )}
                        <span className="ml-2 text-theme-sm font-normal text-gray-500">{[s.customer.document, s.customer.phone && formatPhone(s.customer.phone)].filter(Boolean).join(" · ")}</span>
                      </p>
                    ) : (
                      <p className="text-base font-medium text-gray-800 dark:text-white/90">Venta de mostrador</p>
                    )}
                  </div>
                  {editable && (
                    <button type="button" onClick={() => setChangingCustomer(true)} className="text-theme-sm font-medium text-brand-600 hover:underline">
                      Cambiar cliente
                    </button>
                  )}
                </>
              )}
            </div>
          </Card>

          {/* Items */}
          <Card>
            <div className="border-b border-gray-100 px-5 py-4 dark:border-gray-800">
              <h2 className="text-base font-semibold text-gray-800 dark:text-white/90">Qué se vende</h2>
              {editable && <AddItem saleId={s.id} branchId={s.branch?.id ?? ""} onAdd={(body) => change.mutate({ path: `sales/${s.id}/items`, body })} pending={change.isPending} />}
            </div>
            {s.items.length === 0 ? (
              <p className="px-5 py-8 text-center text-theme-sm text-gray-500">Aún no hay ítems. Busca un producto o servicio arriba.</p>
            ) : (
              <ul className="divide-y divide-gray-100 dark:divide-gray-800">
                {s.items.map((item) => (
                  <ItemRow
                    // New values from the API (e.g. a discount on everything) reset the inputs.
                    key={`${item.id}:${item.quantity}:${item.unit_price}:${item.discount}`}
                    item={item}
                    editable={editable}
                    onSave={(body) => change.mutate({ path: `sale-items/${item.id}`, method: "PATCH", body })}
                    onRemove={() => change.mutate({ path: `sale-items/${item.id}`, method: "DELETE" })}
                  />
                ))}
              </ul>
            )}
            {editable && s.items.length > 0 && <DiscountAll onApply={(percent) => change.mutate({ path: `sales/${s.id}/discount`, body: { percent } })} />}
          </Card>
        </div>

        {/* Totals and payments */}
        <div className="space-y-6">
          <Card>
            <dl className="space-y-1.5 px-5 py-4 text-theme-sm">
              {s.discount_total > 0 && (
                <>
                  <Row label="Subtotal" value={formatCop(s.subtotal)} />
                  <Row label="Descuentos" value={`- ${formatCop(s.discount_total)}`} />
                </>
              )}
              {s.tax_total > 0 && <Row label="IVA incluido" value={formatCop(s.tax_total)} muted />}
              <div className="flex items-baseline justify-between border-t border-gray-100 pt-2 dark:border-gray-800">
                <dt className="font-semibold text-gray-800 dark:text-white/90">Total</dt>
                <dd className="text-2xl font-semibold text-gray-900 tabular-nums dark:text-white">{formatCop(s.total)}</dd>
              </div>
              <Row label="Pagado" value={formatCop(s.paid_total)} />
              {s.status.value !== "void" && (
                <div className="flex items-baseline justify-between">
                  <dt className="font-semibold text-gray-800 dark:text-white/90">Saldo</dt>
                  <dd className={cn("text-lg font-semibold tabular-nums", s.balance > 0 ? "text-warning-700" : "text-success-600")}>{s.balance > 0 ? formatCop(s.balance) : "Pagada"}</dd>
                </div>
              )}
            </dl>
          </Card>

          <Card>
            <div className="border-b border-gray-100 px-5 py-4 dark:border-gray-800">
              <h2 className="text-base font-semibold text-gray-800 dark:text-white/90">Pagos</h2>
            </div>
            {s.payments.length > 0 && (
              <ul className="divide-y divide-gray-100 dark:divide-gray-800">
                {s.payments.map((p) => (
                  <li key={p.id} className={cn("px-5 py-3", p.voided && "opacity-60")}>
                    <div className="flex items-start justify-between gap-3">
                      <div>
                        <p className={cn("text-theme-sm font-medium text-gray-800 dark:text-white/90", p.voided && "line-through")}>
                          {p.method.label}
                          {p.reference ? <span className="font-normal text-gray-500"> · {p.reference}</span> : null}
                        </p>
                        <p className="text-theme-xs text-gray-500">
                          {when(p.paid_at)}
                          {p.received_by ? ` · ${p.received_by}` : ""}
                        </p>
                        {p.voided && <p className="text-theme-xs text-error-600">Anulado: {p.void_reason}</p>}
                      </div>
                      <div className="text-right">
                        <p className={cn("text-theme-sm font-semibold tabular-nums", p.voided && "line-through")}>{formatCop(p.amount)}</p>
                        {canVoid && !p.voided && s.status.value !== "void" && (
                          <button
                            type="button"
                            onClick={() => setVoiding({ kind: "payment", id: p.id })}
                            className="mt-1.5 rounded-lg border border-error-200 px-2.5 py-1 text-theme-xs font-medium whitespace-nowrap text-error-600 hover:bg-error-50 dark:border-error-500/30 dark:hover:bg-error-500/10"
                          >
                            Anular pago
                          </button>
                        )}
                      </div>
                    </div>
                  </li>
                ))}
              </ul>
            )}
            {canSell && s.status.value !== "void" && s.balance > 0 && (
              // key: a new balance starts the form again with it as the amount.
              <PaymentForm key={s.balance} balance={s.balance} pending={change.isPending} onPay={(body) => change.mutate({ path: `sales/${s.id}/payments`, body })} />
            )}
            {s.payments.length === 0 && !(canSell && s.status.value !== "void" && s.balance > 0) && <p className="px-5 py-6 text-center text-theme-sm text-gray-500">Sin pagos.</p>}
          </Card>
        </div>
      </div>

      {voiding && (
        <VoidModal
          title={voiding.kind === "sale" ? `Anular la venta ${saleNumber(s.number)}` : "Anular este pago"}
          description={voiding.kind === "sale" ? "Los productos vuelven al inventario. La venta no se borra: queda como anulada con el motivo." : "El pago queda registrado como anulado y el saldo de la venta vuelve a abrirse."}
          pending={change.isPending}
          onClose={() => setVoiding(null)}
          onConfirm={(reason) =>
            change.mutate({ path: voiding.kind === "sale" ? `sales/${s.id}/void` : `payments/${voiding.id}/void`, body: { reason } }, { onSuccess: () => setVoiding(null) })
          }
        />
      )}
    </>
  );
}

function Row({ label, value, muted = false }: { label: string; value: string; muted?: boolean }) {
  return (
    <div className="flex justify-between">
      <dt className="text-gray-500">{label}</dt>
      <dd className={cn("tabular-nums", muted ? "text-gray-500" : "text-gray-800 dark:text-white/90")}>{value}</dd>
    </div>
  );
}

/** Search products and services, or add a free line ("Corte de uñas", 12.000). */
function AddItem({ saleId, branchId, onAdd, pending }: { saleId: string; branchId: string; onAdd: (body: Record<string, unknown>) => void; pending: boolean }) {
  const [q, setQ] = useState("");
  const [open, setOpen] = useState(false);
  const [custom, setCustom] = useState<{ description: string; unit_price: string; quantity: string; tax_rate: string } | null>(null);
  const term = useDeferredValue(q.trim());
  const box = useRef<HTMLDivElement>(null);

  const results = useQuery({
    queryKey: ["sellables", branchId, term],
    queryFn: () => api<{ data: Sellable[] }>(`sellables?branch_id=${branchId}&q=${encodeURIComponent(term)}`).then((r) => r.data),
    enabled: open && !!branchId,
  });

  useEffect(() => {
    if (!open) return;
    const onDown = (e: MouseEvent) => box.current && !box.current.contains(e.target as Node) && setOpen(false);
    document.addEventListener("mousedown", onDown);
    return () => document.removeEventListener("mousedown", onDown);
  }, [open]);

  const pick = (it: Sellable) => {
    onAdd({ type: it.type, [it.type === "product" ? "product_id" : "service_id"]: it.id, quantity: 1 });
    setQ("");
    setOpen(false);
  };

  return (
    <div className="mt-3" key={saleId}>
      {!custom ? (
        <div className="flex flex-wrap gap-2">
          <div ref={box} className="relative min-w-0 flex-1">
            <Input
              value={q}
              onChange={(e) => {
                setQ(e.target.value);
                setOpen(true);
              }}
              onFocus={() => setOpen(true)}
              placeholder="Buscar producto o servicio…"
              aria-label="Buscar producto o servicio"
            />
            {open && (
              <ul className="absolute z-20 mt-1 max-h-72 w-full overflow-y-auto rounded-xl border border-gray-200 bg-white shadow-theme-lg dark:border-gray-700 dark:bg-gray-900">
                {results.isLoading ? (
                  <li className="px-4 py-3 text-theme-sm text-gray-500">Buscando…</li>
                ) : !results.data?.length ? (
                  <li className="px-4 py-3 text-theme-sm text-gray-500">Nada coincide. Usa «Cargo libre» para algo que no está en la lista.</li>
                ) : (
                  results.data.map((it) => (
                    <li key={`${it.type}-${it.id}`}>
                      <button type="button" onClick={() => pick(it)} disabled={pending} className="flex w-full items-center justify-between gap-3 px-4 py-2.5 text-left hover:bg-gray-50 dark:hover:bg-white/5">
                        <span className="min-w-0">
                          <span className="block truncate text-theme-sm font-medium text-gray-800 dark:text-white/90">{it.name}</span>
                          <span className="text-theme-xs text-gray-500">
                            {it.detail}
                            {it.stock !== null && it.unit ? ` · hay ${qtyUnit(it.stock, it.unit)}` : ""}
                          </span>
                        </span>
                        <span className="shrink-0 text-theme-sm font-semibold tabular-nums">{formatCop(it.price)}</span>
                      </button>
                    </li>
                  ))
                )}
              </ul>
            )}
          </div>
          <Button variant="outline" onClick={() => setCustom({ description: "", unit_price: "", quantity: "1", tax_rate: "0" })}>
            Cargo libre
          </Button>
        </div>
      ) : (
        <div className="grid gap-3 rounded-xl border border-gray-200 p-3 sm:grid-cols-[1fr_120px_80px_110px] dark:border-gray-700">
          <Input value={custom.description} onChange={(e) => setCustom({ ...custom, description: e.target.value })} placeholder="Descripción" aria-label="Descripción" autoFocus />
          <Input type="number" min={0} value={custom.unit_price} onChange={(e) => setCustom({ ...custom, unit_price: e.target.value })} placeholder="Precio" aria-label="Precio" />
          <Input type="number" min={1} value={custom.quantity} onChange={(e) => setCustom({ ...custom, quantity: e.target.value })} aria-label="Cantidad" />
          <Select value={custom.tax_rate} onChange={(e) => setCustom({ ...custom, tax_rate: e.target.value })} aria-label="IVA">
            <option value="0">Sin IVA</option>
            <option value="5">IVA 5 %</option>
            <option value="19">IVA 19 %</option>
          </Select>
          <div className="flex gap-2 sm:col-span-4 sm:justify-end">
            <Button variant="ghost" size="sm" onClick={() => setCustom(null)}>
              Cancelar
            </Button>
            <Button
              size="sm"
              disabled={!custom.description.trim() || custom.unit_price === "" || pending}
              onClick={() => {
                onAdd({ type: "custom", description: custom.description, unit_price: Number(custom.unit_price), quantity: Number(custom.quantity || 1), tax_rate: Number(custom.tax_rate) });
                setCustom(null);
              }}
            >
              Agregar
            </Button>
          </div>
        </div>
      )}
    </div>
  );
}

/** A line: quantity, price and discount editable while the sale is open. */
function ItemRow({ item, editable, onSave, onRemove }: { item: SaleItem; editable: boolean; onSave: (b: { quantity: number; unit_price: number; discount: number }) => void; onRemove: () => void }) {
  const [quantity, setQuantity] = useState(String(item.quantity));
  const [price, setPrice] = useState(String(item.unit_price));
  const [discount, setDiscount] = useState(String(item.discount));
  const dirty = Number(quantity) !== item.quantity || Number(price) !== item.unit_price || Number(discount || 0) !== item.discount;

  return (
    <li className="px-5 py-3">
      <div className="flex flex-wrap items-center gap-3">
        <div className="min-w-0 flex-1">
          <p className="truncate text-theme-sm font-medium text-gray-800 dark:text-white/90">{item.description}</p>
          <p className="text-theme-xs text-gray-500">
            {item.type === "service" ? "Servicio" : item.type === "custom" ? "Cargo libre" : item.moves_stock ? "Producto · sale del inventario" : "Producto"}
            {item.tax_rate ? ` · IVA ${item.tax_rate} % incluido` : ""}
          </p>
          {item.origin && (
            <p className="text-theme-xs text-brand-600 dark:text-brand-400">
              {item.origin.kind === "clinical_record" && item.origin.pet_id ? (
                <Link href={`/app/mascotas/${item.origin.pet_id}`} className="hover:underline">
                  Desde la historia: {item.origin.label}
                </Link>
              ) : (
                item.origin.label
              )}
            </p>
          )}
        </div>
        {editable ? (
          <div className="flex items-center gap-2">
            <label className="text-theme-xs text-gray-500">
              Cant.
              <input type="number" min={0} step="any" value={quantity} onChange={(e) => setQuantity(e.target.value)} className="ml-1 h-9 w-16 rounded-lg border border-gray-300 px-2 text-right text-theme-sm dark:border-gray-700 dark:bg-gray-900" />
            </label>
            <label className="text-theme-xs text-gray-500">
              Precio
              <input type="number" min={0} value={price} onChange={(e) => setPrice(e.target.value)} className="ml-1 h-9 w-24 rounded-lg border border-gray-300 px-2 text-right text-theme-sm dark:border-gray-700 dark:bg-gray-900" />
            </label>
            <label className="text-theme-xs text-gray-500">
              Desc.
              <input type="number" min={0} value={discount} onChange={(e) => setDiscount(e.target.value)} className="ml-1 h-9 w-20 rounded-lg border border-gray-300 px-2 text-right text-theme-sm dark:border-gray-700 dark:bg-gray-900" />
            </label>
          </div>
        ) : (
          <p className="text-theme-sm text-gray-500 tabular-nums">
            {item.quantity} × {formatCop(item.unit_price)}
            {item.discount ? ` − ${formatCop(item.discount)}` : ""}
          </p>
        )}
        <p className="w-24 text-right text-theme-sm font-semibold tabular-nums">{formatCop(item.total)}</p>
        {editable && (
          <div className="flex gap-1">
            {dirty && (
              <Button size="sm" onClick={() => onSave({ quantity: Number(quantity), unit_price: Number(price), discount: Number(discount || 0) })}>
                Guardar
              </Button>
            )}
            <button type="button" onClick={onRemove} aria-label={`Quitar ${item.description}`} className="rounded-lg px-2 py-1 text-gray-400 hover:bg-error-50 hover:text-error-600">
              ✕
            </button>
          </div>
        )}
      </div>
    </li>
  );
}

function DiscountAll({ onApply }: { onApply: (percent: number) => void }) {
  const [percent, setPercent] = useState("");
  return (
    <div className="flex flex-wrap items-center justify-end gap-2 border-t border-gray-100 px-5 py-3 text-theme-sm text-gray-500 dark:border-gray-800">
      Descuento a toda la venta
      <input type="number" min={0} max={100} value={percent} onChange={(e) => setPercent(e.target.value)} placeholder="%" className="h-9 w-16 rounded-lg border border-gray-300 px-2 text-right dark:border-gray-700 dark:bg-gray-900" aria-label="Porcentaje de descuento" />
      <Button size="sm" variant="outline" disabled={percent === ""} onClick={() => onApply(Number(percent))}>
        Aplicar
      </Button>
    </div>
  );
}

/** Register a payment. Cash helps with the change; other methods ask for a reference. */
function PaymentForm({ balance, pending, onPay }: { balance: number; pending: boolean; onPay: (b: { method: string; amount: number; reference: string | null }) => void }) {
  const [method, setMethod] = useState("cash");
  const [amount, setAmount] = useState(String(balance));
  const [received, setReceived] = useState("");
  const [reference, setReference] = useState("");
  const change = method === "cash" && received !== "" ? Number(received) - Number(amount || 0) : null;

  return (
    <div className="space-y-3 border-t border-gray-100 px-5 py-4 dark:border-gray-800">
      <p className="text-theme-sm font-semibold text-gray-800 dark:text-white/90">Registrar pago</p>
      <div className="flex flex-wrap gap-1.5">
        {PAYMENT_METHODS.map(([v, l]) => (
          <button
            key={v}
            type="button"
            onClick={() => setMethod(v)}
            aria-pressed={method === v}
            className={cn("rounded-full border px-3 py-1 text-theme-xs font-medium", method === v ? "border-brand-500 bg-brand-500 text-white" : "border-gray-300 text-gray-600 hover:border-brand-300 dark:border-gray-700 dark:text-gray-300")}
          >
            {l}
          </button>
        ))}
      </div>
      <Field label="Valor" htmlFor="pay-amount" hint={`Saldo: ${formatCop(balance)}`}>
        <Input id="pay-amount" type="number" min={0} value={amount} onChange={(e) => setAmount(e.target.value)} />
      </Field>
      {method === "cash" ? (
        <Field label="Recibido" htmlFor="pay-received" optional hint={change !== null ? (change >= 0 ? `Cambio: ${formatCop(change)}` : "Recibido menor que el valor.") : "Para calcular el cambio."}>
          <Input id="pay-received" type="number" min={0} value={received} onChange={(e) => setReceived(e.target.value)} />
        </Field>
      ) : (
        <Field label="Referencia" htmlFor="pay-ref" optional hint="Número de aprobación o de la transferencia.">
          <Input id="pay-ref" value={reference} onChange={(e) => setReference(e.target.value)} />
        </Field>
      )}
      <Button
        className="w-full"
        loading={pending}
        disabled={!amount || Number(amount) <= 0}
        onClick={() => {
          onPay({ method, amount: Number(amount), reference: method === "cash" ? null : reference || null });
          setReceived("");
          setReference("");
        }}
      >
        Registrar {formatCop(Number(amount || 0))}
      </Button>
    </div>
  );
}

function VoidModal({ title, description, pending, onClose, onConfirm }: { title: string; description: string; pending: boolean; onClose: () => void; onConfirm: (reason: string) => void }) {
  const [reason, setReason] = useState("");
  return (
    <Modal isOpen onClose={onClose} className="m-4 w-full max-w-md rounded-2xl bg-white p-6 dark:bg-gray-900">
      <h2 className="pr-12 text-lg font-semibold text-gray-800 dark:text-white/90">{title}</h2>
      <p className="mt-1 pr-12 text-theme-sm text-gray-500">{description}</p>
      <div className="mt-4">
        <Field label="Motivo" htmlFor="void-reason">
          <Textarea id="void-reason" rows={3} maxLength={255} value={reason} onChange={(e) => setReason(e.target.value)} autoFocus />
        </Field>
      </div>
      <div className="mt-6 flex justify-end gap-3">
        <Button variant="outline" onClick={onClose}>
          Volver
        </Button>
        <Button variant="danger" loading={pending} disabled={!reason.trim()} onClick={() => onConfirm(reason.trim())}>
          Anular
        </Button>
      </div>
    </Modal>
  );
}
