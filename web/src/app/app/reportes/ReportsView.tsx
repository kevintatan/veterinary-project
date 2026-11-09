"use client";

import ChartCard, { shortDate } from "@/components/charts/ChartCard";
import ChartTable from "@/components/charts/ChartTable";
import { FONT_FAMILY, useChartTokens } from "@/components/charts/chartTheme";
import { Card, CardHeader, EmptyState, Skeleton } from "@/components/common/Card";
import PageHeader from "@/components/common/PageHeader";
import { Input, Select } from "@/components/form/fields";
import { useCan, useSession } from "@/components/providers/SessionProvider";
import Button from "@/components/ui/button/Button";
import { ApiError, api } from "@/lib/api";
import { saleNumber } from "@/lib/sales";
import { formatCop } from "@/lib/settings";
import { todayIn } from "@/lib/dates";
import { cn } from "@/utils";
import { keepPreviousData, useQuery } from "@tanstack/react-query";
import type { ApexOptions } from "apexcharts";
import dynamic from "next/dynamic";
import Link from "next/link";
import { useState } from "react";

// ApexCharts touches `window`: client-only.
const Chart = dynamic(() => import("react-apexcharts"), { ssr: false, loading: () => <Skeleton className="h-full w-full" /> });

type Labelled = { value: string; label: string };
type DailyClose = {
  date: string;
  collected: number;
  collected_from_earlier_sales: number;
  by_method: { method: Labelled; count: number; total: number }[];
  by_receiver: { name: string; count: number; total: number }[];
  sales: { count: number; total: number };
  pending: { total: number; list: { id: string; number: number; customer: string | null; total: number; balance: number }[] };
  voided_sales: { id: string; number: number; customer: string | null; total: number; paid: number; reason: string | null }[];
  voided_payments: { number: number; method: string; amount: number; reason: string | null }[];
};
type Totals = { sales_count: number; sold: number; tax: number; discounts: number; pending: number; collected: number; average_ticket: number; voided_count: number };
type SalesReport = {
  from: string;
  to: string;
  days: number;
  summary: Totals;
  previous: Totals;
  daily: { date: string; sold: number; collected: number }[];
  top_services: { name: string; quantity: number; total: number }[];
  top_products: { name: string; quantity: number; total: number }[];
  by_type: { service: number; product: number; custom: number };
  by_seller: { name: string; count: number; total: number }[];
  by_method: { method: Labelled; total: number }[];
};

/** Reportes (docs/inventario.md, V4): the daily close for whoever charges; sales and export for the admin. */
export default function ReportsView() {
  const canReports = useCan("reports.view");
  const [tab, setTab] = useState<"close" | "sales">("close");

  return (
    <>
      <PageHeader title="Reportes" subtitle="Cierre del día y ventas del periodo. «Ventas» es lo vendido; «recaudado» es el dinero que entró." />
      {canReports && (
        <div className="mb-5 flex gap-1 border-b border-gray-200 print:hidden dark:border-gray-800" role="tablist">
          {(
            [
              ["close", "Cierre del día"],
              ["sales", "Ventas"],
            ] as const
          ).map(([key, label]) => (
            <button
              key={key}
              type="button"
              role="tab"
              aria-selected={tab === key}
              onClick={() => setTab(key)}
              className={cn("-mb-px border-b-2 px-4 py-2.5 text-sm font-medium", tab === key ? "border-brand-500 text-brand-700 dark:text-brand-400" : "border-transparent text-gray-500 hover:text-gray-800 dark:hover:text-white")}
            >
              {label}
            </button>
          ))}
        </div>
      )}
      {tab === "close" || !canReports ? <DailyCloseView /> : <SalesReportView />}
    </>
  );
}

function BranchSelect({ value, onChange }: { value: string; onChange: (v: string) => void }) {
  const { branches } = useSession();
  if (branches.length < 2) return null;
  return (
    <Select aria-label="Sede" value={value} onChange={(e) => onChange(e.target.value)} className="w-auto">
      <option value="">Todas las sedes</option>
      {branches.map((b) => (
        <option key={b.id} value={b.id}>
          {b.name}
        </option>
      ))}
    </Select>
  );
}

function Tile({ label, value, hint, tone }: { label: string; value: string; hint?: React.ReactNode; tone?: string }) {
  return (
    <Card className="px-5 py-4">
      <p className="text-theme-sm text-gray-500">{label}</p>
      <p className={cn("mt-1 text-2xl font-semibold text-gray-900 tabular-nums dark:text-white", tone)}>{value}</p>
      {hint && <p className="mt-0.5 text-theme-xs text-gray-500">{hint}</p>}
    </Card>
  );
}

// ---- Daily close -------------------------------------------------------------------

function DailyCloseView() {
  const { clinic } = useSession();
  const [date, setDate] = useState(() => todayIn(clinic.timezone));
  const [branchId, setBranchId] = useState("");
  const close = useQuery({
    queryKey: ["reports", "daily-close", date, branchId],
    queryFn: () => api<{ data: DailyClose }>(`reports/daily-close?date=${date}${branchId ? `&branch_id=${branchId}` : ""}`).then((r) => r.data),
    placeholderData: keepPreviousData,
  });
  const c = close.data;
  const longDate = new Date(`${date}T12:00:00Z`).toLocaleDateString("es-CO", { weekday: "long", day: "numeric", month: "long", year: "numeric", timeZone: "UTC" });

  return (
    <div className="space-y-5">
      <div className="flex flex-wrap items-center gap-3 print:hidden">
        <Input type="date" value={date} onChange={(e) => e.target.value && setDate(e.target.value)} className="w-auto" aria-label="Día" />
        <BranchSelect value={branchId} onChange={setBranchId} />
        <Button variant="outline" size="sm" onClick={() => window.print()} className="ml-auto">
          Imprimir cierre
        </Button>
      </div>
      <h2 className="hidden text-lg font-semibold print:block">
        Cierre del día · <span className="first-letter:uppercase">{longDate}</span> · {clinic.name}
      </h2>

      {!c ? (
        <Skeleton className="h-72" />
      ) : (
        <>
          <div className="grid gap-4 sm:grid-cols-3">
            <Tile
              label="Recaudado"
              value={formatCop(c.collected)}
              hint={c.collected_from_earlier_sales > 0 ? `Incluye ${formatCop(c.collected_from_earlier_sales)} de ventas de días anteriores` : "Dinero que entró ese día"}
            />
            <Tile label="Ventas del día" value={formatCop(c.sales.total)} hint={`${c.sales.count} ${c.sales.count === 1 ? "venta" : "ventas"} (sin anuladas)`} />
            <Tile label="Quedó por cobrar" value={formatCop(c.pending.total)} hint="De las ventas de ese día" tone={c.pending.total > 0 ? "text-warning-700" : undefined} />
          </div>

          <div className="grid gap-5 lg:grid-cols-2">
            <Card>
              <CardHeader title="Por medio de pago" description="Para cuadrar la caja y los extractos." />
              {c.by_method.length === 0 ? (
                <p className="px-5 pb-5 text-theme-sm text-gray-500">Sin pagos ese día.</p>
              ) : (
                <table className="w-full text-theme-sm">
                  <tbody className="divide-y divide-gray-100 dark:divide-gray-800">
                    {c.by_method.map((m) => (
                      <tr key={m.method.value}>
                        <td className="px-5 py-2.5 text-gray-700 dark:text-gray-300">
                          {m.method.label} <span className="text-theme-xs text-gray-400">· {m.count}</span>
                        </td>
                        <td className="px-5 py-2.5 text-right font-semibold tabular-nums">{formatCop(m.total)}</td>
                      </tr>
                    ))}
                    <tr className="bg-gray-50 dark:bg-white/2">
                      <td className="px-5 py-2.5 font-semibold">Total</td>
                      <td className="px-5 py-2.5 text-right font-semibold tabular-nums">{formatCop(c.collected)}</td>
                    </tr>
                  </tbody>
                </table>
              )}
            </Card>

            <Card>
              <CardHeader title="Quién recibió" description="Pagos registrados por cada persona." />
              {c.by_receiver.length === 0 ? (
                <p className="px-5 pb-5 text-theme-sm text-gray-500">Sin pagos ese día.</p>
              ) : (
                <table className="w-full text-theme-sm">
                  <tbody className="divide-y divide-gray-100 dark:divide-gray-800">
                    {c.by_receiver.map((r) => (
                      <tr key={r.name}>
                        <td className="px-5 py-2.5 text-gray-700 dark:text-gray-300">
                          {r.name} <span className="text-theme-xs text-gray-400">· {r.count}</span>
                        </td>
                        <td className="px-5 py-2.5 text-right font-semibold tabular-nums">{formatCop(r.total)}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              )}
            </Card>
          </div>

          <Card>
            <CardHeader title="Por cobrar" description="Ventas de ese día con saldo pendiente." />
            {c.pending.list.length === 0 ? (
              <p className="px-5 pb-5 text-theme-sm text-gray-500">Todo lo vendido ese día quedó pagado.</p>
            ) : (
              <ul className="divide-y divide-gray-100 dark:divide-gray-800">
                {c.pending.list.map((s) => (
                  <li key={s.id} className="flex items-center justify-between px-5 py-2.5 text-theme-sm">
                    <Link href={`/app/ventas/${s.id}`} className="hover:underline">
                      {saleNumber(s.number)} · {s.customer ?? "Mostrador"}
                    </Link>
                    <span className="font-semibold text-warning-700 tabular-nums">{formatCop(s.balance)}</span>
                  </li>
                ))}
              </ul>
            )}
          </Card>

          {(c.voided_sales.length > 0 || c.voided_payments.length > 0) && (
            <Card>
              <CardHeader title="Anulaciones" description="Ventas y pagos anulados ese día, con su motivo." />
              <ul className="divide-y divide-gray-100 text-theme-sm dark:divide-gray-800">
                {c.voided_sales.map((s) => (
                  <li key={s.id} className="px-5 py-2.5">
                    <Link href={`/app/ventas/${s.id}`} className="font-medium hover:underline">
                      Venta {saleNumber(s.number)}
                    </Link>{" "}
                    · {formatCop(s.total)} · {s.reason}
                    {s.paid > 0 && <span className="text-error-600"> · tenía pagos por {formatCop(s.paid)} (a devolver)</span>}
                  </li>
                ))}
                {c.voided_payments.map((p, i) => (
                  <li key={i} className="px-5 py-2.5">
                    Pago de la venta {saleNumber(p.number)} · {p.method} {formatCop(p.amount)} · {p.reason}
                  </li>
                ))}
              </ul>
            </Card>
          )}
        </>
      )}
    </div>
  );
}

// ---- Sales report --------------------------------------------------------------------

const PRESETS = [
  ["today", "Hoy"],
  ["7", "7 días"],
  ["month", "Este mes"],
  ["last-month", "Mes anterior"],
] as const;

function rangeFor(preset: string, today: string): [string, string] {
  const d = new Date(`${today}T12:00:00Z`);
  const iso = (x: Date) => x.toISOString().slice(0, 10);
  if (preset === "7") return [iso(new Date(d.getTime() - 6 * 86_400_000)), today];
  if (preset === "month") return [today.slice(0, 8) + "01", today];
  if (preset === "last-month") {
    const first = new Date(Date.UTC(d.getUTCFullYear(), d.getUTCMonth() - 1, 1, 12));
    const last = new Date(Date.UTC(d.getUTCFullYear(), d.getUTCMonth(), 0, 12));
    return [iso(first), iso(last)];
  }
  return [today, today];
}

function Delta({ now, before, label }: { now: number; before: number; label: string }) {
  if (before === 0) return <span className="text-gray-400">{now > 0 ? `Sin ventas en ${label}` : `Igual que ${label}`}</span>;
  const pct = Math.round(((now - before) / before) * 100);
  return (
    <span className={pct > 0 ? "text-success-600" : pct < 0 ? "text-error-600" : "text-gray-500"}>
      {pct > 0 ? "▲" : pct < 0 ? "▼" : "="} {Math.abs(pct)} % frente a {label}
    </span>
  );
}

function SalesReportView() {
  const { clinic } = useSession();
  const today = todayIn(clinic.timezone);
  const [preset, setPreset] = useState<string>("month");
  const [[from, to], setRange] = useState(() => rangeFor("month", today));
  const [branchId, setBranchId] = useState("");
  const t = useChartTokens();

  const report = useQuery({
    queryKey: ["reports", "sales", from, to, branchId],
    queryFn: () => api<{ data: SalesReport }>(`reports/sales?from=${from}&to=${to}${branchId ? `&branch_id=${branchId}` : ""}`).then((r) => r.data),
    placeholderData: keepPreviousData,
    retry: false,
  });
  const r = report.data;
  const query = `from=${from}&to=${to}${branchId ? `&branch_id=${branchId}` : ""}`;
  const previousLabel = `los ${r?.days ?? ""} días anteriores`;

  if (report.error instanceof ApiError && report.error.status === 402) {
    return (
      <Card>
        <div className="p-6">
          <p className="text-base font-semibold text-gray-800 dark:text-white/90">Los reportes de ventas están disponibles desde el plan Pro</p>
          <p className="mt-1 text-sm text-gray-600 dark:text-gray-300">Ventas por día, lo más vendido, medios de pago y exportación a Excel para el contador. El cierre del día sigue disponible.</p>
          <Link href="/app/configuracion?seccion=plan" className="mt-4 inline-flex rounded-lg bg-brand-500 px-4 py-2 text-sm font-medium text-white hover:bg-brand-600">
            Ver planes
          </Link>
        </div>
      </Card>
    );
  }

  const options: ApexOptions = {
    chart: { type: "bar", toolbar: { show: false }, fontFamily: FONT_FAMILY, background: "transparent", animations: { speed: 250 } },
    theme: { mode: t.mode },
    colors: [t.single], // one series: the title names it, no legend
    plotOptions: { bar: { columnWidth: (r?.daily.length ?? 0) > 45 ? "70%" : "18px", borderRadius: 4, borderRadiusApplication: "end" } },
    dataLabels: { enabled: false },
    legend: { show: false },
    grid: { borderColor: t.grid, xaxis: { lines: { show: false } } },
    xaxis: {
      categories: (r?.daily ?? []).map((d) => shortDate(d.date)),
      tickAmount: Math.min(r?.daily.length ?? 0, 10),
      labels: { style: { colors: t.axis, fontSize: "12px" }, rotate: 0, hideOverlappingLabels: true },
      axisBorder: { show: false },
      axisTicks: { show: false },
    },
    yaxis: { labels: { style: { colors: t.axis, fontSize: "12px" }, formatter: (v) => formatCop(v) }, forceNiceScale: true, min: 0 },
    tooltip: { theme: t.mode, y: { formatter: (v) => formatCop(v) } },
    states: { hover: { filter: { type: "lighten" } } },
  };

  return (
    <div className="space-y-5">
      <div className="flex flex-wrap items-center gap-3">
        <div className="flex rounded-lg bg-gray-100 p-0.5 dark:bg-white/5">
          {PRESETS.map(([key, label]) => (
            <button
              key={key}
              type="button"
              onClick={() => {
                setPreset(key);
                setRange(rangeFor(key, today));
              }}
              className={cn("rounded-md px-3 py-1.5 text-theme-sm font-medium", preset === key ? "bg-white text-gray-900 shadow-theme-xs dark:bg-gray-800 dark:text-white" : "text-gray-500 hover:text-gray-900")}
            >
              {label}
            </button>
          ))}
        </div>
        <Input type="date" value={from} max={to} onChange={(e) => { setPreset(""); setRange([e.target.value, to]); }} className="w-auto" aria-label="Desde" />
        <Input type="date" value={to} min={from} onChange={(e) => { setPreset(""); setRange([from, e.target.value]); }} className="w-auto" aria-label="Hasta" />
        <BranchSelect value={branchId} onChange={setBranchId} />
        <div className="ml-auto flex gap-2">
          <a href={`/api/backend/reports/sales/export?kind=items&${query}`} download className="rounded-lg border border-gray-300 px-3 py-2 text-theme-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300">
            Exportar ventas (Excel)
          </a>
          <a href={`/api/backend/reports/sales/export?kind=payments&${query}`} download className="rounded-lg border border-gray-300 px-3 py-2 text-theme-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300">
            Exportar pagos (Excel)
          </a>
        </div>
      </div>

      {!r ? (
        <Skeleton className="h-96" />
      ) : (
        <>
          <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <Tile label="Ventas" value={formatCop(r.summary.sold)} hint={<Delta now={r.summary.sold} before={r.previous.sold} label={previousLabel} />} />
            <Tile label="Recaudado" value={formatCop(r.summary.collected)} hint={<Delta now={r.summary.collected} before={r.previous.collected} label={previousLabel} />} />
            <Tile label="Número de ventas" value={String(r.summary.sales_count)} hint={`Promedio por venta: ${formatCop(r.summary.average_ticket)}`} />
            <Tile label="Por cobrar" value={formatCop(r.summary.pending)} hint={`IVA incluido en ventas: ${formatCop(r.summary.tax)}`} tone={r.summary.pending > 0 ? "text-warning-700" : undefined} />
          </div>

          <ChartCard
            title="Ventas por día"
            description="Lo vendido cada día (sin anuladas)."
            table={<ChartTable caption="Ventas por día" columns={["Día", "Vendido", "Recaudado"]} rows={r.daily.map((d) => [shortDate(d.date), formatCop(d.sold), formatCop(d.collected)])} />}
          >
            <div className="h-72 px-3 pb-3">
              {r.daily.every((d) => d.sold === 0) ? (
                <EmptyState title="Sin ventas en este periodo." />
              ) : (
                <Chart type="bar" height="100%" options={options} series={[{ name: "Vendido", data: r.daily.map((d) => d.sold) }]} />
              )}
            </div>
          </ChartCard>

          <div className="grid gap-5 lg:grid-cols-2">
            <RankCard title="Servicios más vendidos" rows={r.top_services} empty="Sin servicios vendidos." />
            <RankCard title="Productos más vendidos" rows={r.top_products} empty="Sin productos vendidos." unitLabel="unid." />
          </div>

          <div className="grid gap-5 lg:grid-cols-3">
            <SmallTable title="Por tipo" rows={[["Servicios", r.by_type.service], ["Productos", r.by_type.product], ["Cargos libres", r.by_type.custom]]} />
            <SmallTable title="Medios de pago" rows={r.by_method.map((m) => [m.method.label, m.total])} empty="Sin pagos." />
            <SmallTable title="Por vendedor" rows={r.by_seller.map((s) => [`${s.name} · ${s.count}`, s.total])} empty="Sin ventas." />
          </div>
          {r.summary.voided_count > 0 && <p className="text-theme-xs text-gray-500">No se cuentan {r.summary.voided_count} ventas anuladas en el periodo.</p>}
        </>
      )}
    </div>
  );
}

function RankCard({ title, rows, empty, unitLabel }: { title: string; rows: { name: string; quantity: number; total: number }[]; empty: string; unitLabel?: string }) {
  const max = Math.max(...rows.map((r) => r.total), 1);
  return (
    <Card>
      <CardHeader title={title} description="Por valor vendido." />
      {rows.length === 0 ? (
        <p className="px-5 pb-5 text-theme-sm text-gray-500">{empty}</p>
      ) : (
        <ul className="space-y-3 px-5 pb-5">
          {rows.map((r) => (
            <li key={r.name}>
              <div className="flex justify-between gap-3 text-theme-sm">
                <span className="truncate text-gray-700 dark:text-gray-300">{r.name}</span>
                <span className="shrink-0 font-semibold tabular-nums">{formatCop(r.total)}</span>
              </div>
              <div className="mt-1 flex items-center gap-2">
                <div className="h-1.5 flex-1 overflow-hidden rounded-full bg-gray-100 dark:bg-white/5">
                  <div className="h-full rounded-full bg-brand-500" style={{ width: `${(r.total / max) * 100}%` }} />
                </div>
                <span className="w-16 text-right text-[11px] text-gray-400 tabular-nums">
                  {r.quantity.toLocaleString("es-CO")} {unitLabel ?? ""}
                </span>
              </div>
            </li>
          ))}
        </ul>
      )}
    </Card>
  );
}

function SmallTable({ title, rows, empty }: { title: string; rows: [string, number][]; empty?: string }) {
  return (
    <Card>
      <CardHeader title={title} />
      {rows.length === 0 ? (
        <p className="px-5 pb-5 text-theme-sm text-gray-500">{empty}</p>
      ) : (
        <table className="w-full text-theme-sm">
          <tbody className="divide-y divide-gray-100 dark:divide-gray-800">
            {rows.map(([label, value]) => (
              <tr key={label}>
                <td className="px-5 py-2 text-gray-700 dark:text-gray-300">{label}</td>
                <td className="px-5 py-2 text-right font-semibold tabular-nums">{formatCop(value)}</td>
              </tr>
            ))}
          </tbody>
        </table>
      )}
    </Card>
  );
}
