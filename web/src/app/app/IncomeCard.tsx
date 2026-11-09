"use client";

import { Card } from "@/components/common/Card";
import { useCan } from "@/components/providers/SessionProvider";
import { api } from "@/lib/api";
import { formatCop } from "@/lib/settings";
import { useQuery } from "@tanstack/react-query";
import Link from "next/link";

type Close = { collected: number; sales: { count: number; total: number }; pending: { total: number }; by_method: { method: { label: string }; total: number }[] };

/** "Ingresos de hoy" on the home screen, for whoever charges (docs/inventario.md, V4). */
export default function IncomeCard() {
  const canCharge = useCan("sales.manage");
  const close = useQuery({
    queryKey: ["reports", "daily-close", "today"],
    queryFn: () => api<{ data: Close }>("reports/daily-close").then((r) => r.data),
    enabled: canCharge,
    refetchInterval: 60_000,
  });
  if (!canCharge || !close.data) return null;
  const c = close.data;

  return (
    <Card className="mb-6">
      <div className="flex flex-wrap items-center gap-x-8 gap-y-3 px-5 py-4">
        <div>
          <p className="text-theme-sm text-gray-500">Recaudado hoy</p>
          <p className="text-2xl font-semibold text-gray-900 tabular-nums dark:text-white">{formatCop(c.collected)}</p>
        </div>
        <div>
          <p className="text-theme-sm text-gray-500">Ventas de hoy</p>
          <p className="text-lg font-semibold text-gray-800 tabular-nums dark:text-white/90">
            {formatCop(c.sales.total)} <span className="text-theme-sm font-normal text-gray-500">· {c.sales.count}</span>
          </p>
        </div>
        <div>
          <p className="text-theme-sm text-gray-500">Por cobrar de hoy</p>
          <p className={`text-lg font-semibold tabular-nums ${c.pending.total > 0 ? "text-warning-700" : "text-gray-800 dark:text-white/90"}`}>{formatCop(c.pending.total)}</p>
        </div>
        {c.by_method.length > 0 && (
          <p className="text-theme-xs text-gray-500">{c.by_method.map((m) => `${m.method.label} ${formatCop(m.total)}`).join(" · ")}</p>
        )}
        <Link href="/app/reportes" className="ml-auto text-theme-sm font-medium text-brand-600 hover:underline">
          Cierre del día →
        </Link>
      </div>
    </Card>
  );
}
