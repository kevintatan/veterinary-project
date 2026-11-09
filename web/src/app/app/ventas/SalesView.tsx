"use client";

import { Card, EmptyState, Skeleton } from "@/components/common/Card";
import PageHeader from "@/components/common/PageHeader";
import { Input } from "@/components/form/fields";
import { useCan, useSession } from "@/components/providers/SessionProvider";
import Button from "@/components/ui/button/Button";
import { api } from "@/lib/api";
import { SALE_BADGE, type SaleSummary, salesKeys, saleNumber } from "@/lib/sales";
import { formatCop } from "@/lib/settings";
import { cn } from "@/utils";
import { keepPreviousData, useQuery } from "@tanstack/react-query";
import Link from "next/link";
import { useDeferredValue, useState } from "react";
import NewSaleModal from "./NewSaleModal";

type Status = "pending" | "paid" | "void" | "all";

const TABS: [Status, string][] = [
  ["pending", "Pendientes"],
  ["paid", "Pagadas"],
  ["void", "Anuladas"],
  ["all", "Todas"],
];

/** Ventas (docs/inventario.md, V2): accounts with what is owed, paid or voided. */
export default function SalesView() {
  const { clinic } = useSession();
  const canSell = useCan("sales.manage");
  const [status, setStatus] = useState<Status>("pending");
  const [q, setQ] = useState("");
  const [from, setFrom] = useState("");
  const [to, setTo] = useState("");
  const [creating, setCreating] = useState(false);
  const search = useDeferredValue(q.trim());
  const filters = { status, q: search, from, to };

  const sales = useQuery({
    queryKey: salesKeys.list(filters),
    queryFn: () => {
      const query = new URLSearchParams({ status, ...(search ? { q: search } : {}), ...(from ? { from } : {}), ...(to ? { to } : {}) });
      return api<{ data: SaleSummary[]; meta: { total: number } }>(`sales?${query}`);
    },
    placeholderData: keepPreviousData,
  });
  const when = (iso: string) => new Date(iso).toLocaleString("es-CO", { day: "numeric", month: "short", hour: "numeric", minute: "2-digit", timeZone: clinic.timezone });
  const list = sales.data?.data ?? [];
  const pendingTotal = status === "pending" ? list.reduce((sum, s) => sum + s.balance, 0) : null;

  return (
    <>
      <PageHeader
        title="Ventas"
        subtitle="Cuentas de los tutores y ventas de mostrador. Los comprobantes no son factura electrónica."
        actions={
          canSell && (
            <Button size="sm" onClick={() => setCreating(true)}>
              Nueva venta
            </Button>
          )
        }
      />

      <div className="mb-4 flex flex-wrap items-center gap-3">
        <div className="flex rounded-lg bg-gray-100 p-0.5 dark:bg-white/5" role="tablist">
          {TABS.map(([key, label]) => (
            <button
              key={key}
              type="button"
              role="tab"
              aria-selected={status === key}
              onClick={() => setStatus(key)}
              className={cn("rounded-md px-3 py-1.5 text-theme-sm font-medium", status === key ? "bg-white text-gray-900 shadow-theme-xs dark:bg-gray-800 dark:text-white" : "text-gray-500 hover:text-gray-900 dark:text-gray-400")}
            >
              {label}
            </button>
          ))}
        </div>
        <Input value={q} onChange={(e) => setQ(e.target.value)} placeholder="Número o nombre del cliente…" className="max-w-xs" aria-label="Buscar venta" />
        <label className="flex items-center gap-2 text-theme-sm text-gray-500">
          Desde
          <Input type="date" value={from} onChange={(e) => setFrom(e.target.value)} className="w-auto" />
        </label>
        <label className="flex items-center gap-2 text-theme-sm text-gray-500">
          Hasta
          <Input type="date" value={to} onChange={(e) => setTo(e.target.value)} className="w-auto" />
        </label>
      </div>

      {pendingTotal !== null && list.length > 0 && (
        <p className="mb-3 text-theme-sm text-gray-600 dark:text-gray-300">
          Por cobrar en esta lista: <b>{formatCop(pendingTotal)}</b>
        </p>
      )}

      {sales.isLoading ? (
        <Skeleton className="h-72" />
      ) : list.length === 0 ? (
        <Card>
          <EmptyState title={status === "pending" ? "No hay cuentas pendientes" : "No hay ventas con estos filtros"} description={canSell ? "Crea una venta con el botón «Nueva venta»." : undefined} />
        </Card>
      ) : (
        <Card className="overflow-hidden">
          <div className="overflow-x-auto">
            <table className="w-full min-w-[640px] text-left text-theme-sm">
              <thead className="border-b border-gray-100 bg-gray-50 text-theme-xs text-gray-500 uppercase dark:border-gray-800 dark:bg-white/2">
                <tr>
                  <th className="px-5 py-3 font-medium">Venta</th>
                  <th className="px-5 py-3 font-medium">Cliente</th>
                  <th className="px-5 py-3 font-medium">Estado</th>
                  <th className="px-5 py-3 text-right font-medium">Total</th>
                  <th className="px-5 py-3 text-right font-medium">Saldo</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-100 dark:divide-gray-800">
                {list.map((s) => (
                  <tr key={s.id} className="hover:bg-gray-50 dark:hover:bg-white/2">
                    <td className="px-5 py-3">
                      <Link href={`/app/ventas/${s.id}`} className="font-medium text-gray-800 hover:underline dark:text-white/90">
                        {saleNumber(s.number)}
                      </Link>
                      <p className="text-theme-xs text-gray-500">
                        {when(s.sold_at)}
                        {s.seller ? ` · ${s.seller}` : ""}
                      </p>
                    </td>
                    <td className="px-5 py-3 text-gray-700 dark:text-gray-300">{s.customer?.name ?? <span className="text-gray-400">Mostrador</span>}</td>
                    <td className="px-5 py-3">
                      <span className={cn("rounded-full px-2.5 py-0.5 text-theme-xs font-medium", SALE_BADGE[s.status.value])}>{s.status.label}</span>
                    </td>
                    <td className="px-5 py-3 text-right tabular-nums">{formatCop(s.total)}</td>
                    <td className={cn("px-5 py-3 text-right font-semibold tabular-nums", s.balance > 0 ? "text-warning-700" : "text-gray-400")}>{s.balance > 0 ? formatCop(s.balance) : "—"}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </Card>
      )}

      {creating && <NewSaleModal onClose={() => setCreating(false)} />}
    </>
  );
}
