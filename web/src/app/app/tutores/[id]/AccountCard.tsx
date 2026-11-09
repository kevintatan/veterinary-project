"use client";

import { Card } from "@/components/common/Card";
import { useCan, useSession } from "@/components/providers/SessionProvider";
import Button from "@/components/ui/button/Button";
import { api } from "@/lib/api";
import { SALE_BADGE, type SaleSummary, salesKeys, saleNumber } from "@/lib/sales";
import { formatCop } from "@/lib/settings";
import { cn } from "@/utils";
import { useQuery } from "@tanstack/react-query";
import Link from "next/link";
import { useState } from "react";
import NewSaleModal from "../../ventas/NewSaleModal";

/** The owner's account at the clinic: what they owe and their recent sales. */
export default function AccountCard({ owner }: { owner: { id: string; full_name: string } }) {
  const { clinic } = useSession();
  const canView = useCan("sales.view");
  const canSell = useCan("sales.manage");
  const [creating, setCreating] = useState(false);

  const account = useQuery({
    queryKey: salesKeys.account(owner.id),
    queryFn: () => api<{ data: { balance: number; pending_count: number; sales: SaleSummary[] } }>(`owners/${owner.id}/account`).then((r) => r.data),
    enabled: canView,
  });
  if (!canView) return null;
  const a = account.data;

  return (
    <Card>
      <div className="flex items-center justify-between gap-3 border-b border-gray-100 px-5 py-4 dark:border-gray-800">
        <div>
          <h3 className="text-base font-semibold text-gray-800 dark:text-white/90">Cuenta</h3>
          <p className={cn("text-theme-sm", a && a.balance > 0 ? "font-semibold text-warning-700" : "text-gray-500")}>
            {!a ? "…" : a.balance > 0 ? `Debe ${formatCop(a.balance)}${a.pending_count > 1 ? ` en ${a.pending_count} ventas` : ""}` : "Al día"}
          </p>
        </div>
        {canSell && (
          <Button size="sm" variant="outline" onClick={() => setCreating(true)}>
            Nueva venta
          </Button>
        )}
      </div>
      {a && a.sales.length > 0 ? (
        <ul className="divide-y divide-gray-100 dark:divide-gray-800">
          {a.sales.slice(0, 6).map((s) => (
            <li key={s.id}>
              <Link href={`/app/ventas/${s.id}`} className="flex items-center justify-between gap-3 px-5 py-2.5 hover:bg-gray-50 dark:hover:bg-white/2">
                <span>
                  <span className="block text-theme-sm font-medium text-gray-800 dark:text-white/90">{saleNumber(s.number)}</span>
                  <span className="text-theme-xs text-gray-500">{new Date(s.sold_at).toLocaleDateString("es-CO", { day: "numeric", month: "short", year: "numeric", timeZone: clinic.timezone })}</span>
                </span>
                <span className="text-right">
                  <span className="block text-theme-sm tabular-nums">{formatCop(s.total)}</span>
                  <span className={cn("rounded-full px-2 py-0.5 text-[11px] font-medium", SALE_BADGE[s.status.value])}>{s.balance > 0 ? `Debe ${formatCop(s.balance)}` : s.status.label}</span>
                </span>
              </Link>
            </li>
          ))}
        </ul>
      ) : (
        <p className="px-5 py-6 text-center text-theme-sm text-gray-500">Sin compras todavía.</p>
      )}
      {creating && <NewSaleModal owner={owner} onClose={() => setCreating(false)} />}
    </Card>
  );
}
