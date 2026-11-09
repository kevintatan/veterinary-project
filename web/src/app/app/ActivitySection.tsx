"use client";

import ChartCard, { shortDate } from "@/components/charts/ChartCard";
import ChartTable from "@/components/charts/ChartTable";
import { FONT_FAMILY, useChartTokens } from "@/components/charts/chartTheme";
import { Card, EmptyState, Skeleton } from "@/components/common/Card";
import { api } from "@/lib/api";
import { cn } from "@/utils";
import { keepPreviousData, useQuery } from "@tanstack/react-query";
import type { ApexOptions } from "apexcharts";
import dynamic from "next/dynamic";
import { useState } from "react";

// ApexCharts touches `window`: client-only.
const Chart = dynamic(() => import("react-apexcharts"), { ssr: false, loading: () => <Skeleton className="h-full w-full" /> });

type Metric = { value: number | null; previous: number | null };
type Stats = {
  range: { days: number; from: string; to: string };
  totals: { completed: Metric; no_show_rate: Metric; cancelled: Metric; new_patients: Metric };
  daily: { date: string; completed: number; missed: number; upcoming: number }[];
  top_services: { name: string; total: number }[];
};

const RANGES = [7, 30, 90] as const;

/**
 * "Actividad": how the agenda is going over a period, compared with the previous one.
 * One period filter above, scoping every figure and chart below (dataviz: filters).
 */
export default function ActivitySection() {
  const [days, setDays] = useState<(typeof RANGES)[number]>(30);

  const { data, isLoading, isError, isFetching } = useQuery({
    queryKey: ["dashboard", "stats", days],
    queryFn: () => api<{ data: Stats }>(`dashboard/stats?days=${days}`).then((r) => r.data),
    placeholderData: keepPreviousData, // refetch keeps the previous render (no skeleton flash)
  });

  return (
    <section aria-labelledby="activity-title" className="mt-8">
      <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div>
          <h2 id="activity-title" className="text-base font-semibold text-gray-800 dark:text-white/90">
            Actividad
          </h2>
          <p className="text-theme-sm text-gray-500 dark:text-gray-400">Comparada con los {days} días anteriores.</p>
        </div>
        <div role="group" aria-label="Periodo" className="inline-flex rounded-lg bg-gray-100 p-0.5 dark:bg-gray-900">
          {RANGES.map((range) => (
            <button
              key={range}
              type="button"
              aria-pressed={days === range}
              onClick={() => setDays(range)}
              className={cn(
                "rounded-md px-3 py-2 text-theme-sm font-medium transition",
                days === range
                  ? "bg-white text-gray-900 shadow-theme-xs dark:bg-gray-800 dark:text-white"
                  : "text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white",
              )}
            >
              {range} días
            </button>
          ))}
        </div>
      </div>

      {isError ? (
        <Card>
          <EmptyState title="No fue posible cargar la actividad." description="Intenta de nuevo en un momento." />
        </Card>
      ) : (
        <div className={cn("transition-opacity", isFetching && !isLoading && "opacity-60")}>
          <div className="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
            <StatTile label="Citas atendidas" metric={data?.totals.completed} upIsGood loading={isLoading} />
            <StatTile label="Tasa de inasistencia" metric={data?.totals.no_show_rate} unit="%" upIsGood={false} loading={isLoading} deltaInPoints />
            <StatTile label="Pacientes nuevos" metric={data?.totals.new_patients} upIsGood loading={isLoading} />
            <StatTile label="Cancelaciones" metric={data?.totals.cancelled} upIsGood={false} loading={isLoading} />
          </div>

          <div className="grid gap-6 xl:grid-cols-3">
            <DailyChart stats={data} loading={isLoading} />
            <ServicesChart stats={data} loading={isLoading} />
          </div>
        </div>
      )}
    </section>
  );
}

// ---- Stat tile -------------------------------------------------------------------

function StatTile({
  label,
  metric,
  unit = "",
  upIsGood,
  loading,
  deltaInPoints = false,
}: {
  label: string;
  metric?: Metric;
  unit?: string;
  upIsGood: boolean;
  loading: boolean;
  deltaInPoints?: boolean;
}) {
  const value = metric?.value ?? null;
  const previous = metric?.previous ?? null;
  const delta = value !== null && previous !== null ? Math.round((value - previous) * 10) / 10 : null;
  const good = delta === null || delta === 0 ? null : (delta > 0) === upIsGood;

  return (
    <Card className="px-5 py-4">
      <p className="text-theme-sm text-gray-500 dark:text-gray-400">{label}</p>
      {loading ? (
        <Skeleton className="mt-2 h-8 w-16" />
      ) : (
        <>
          <p className="mt-1 text-title-sm font-semibold text-gray-800 dark:text-white/90">
            {value === null ? "—" : `${value.toLocaleString("es-CO")}${unit}`}
          </p>
          <p className="mt-1 text-theme-xs text-gray-500 dark:text-gray-400">
            {delta === null ? (
              "Sin datos del periodo anterior"
            ) : delta === 0 ? (
              "Igual que el periodo anterior"
            ) : (
              <>
                <span className={cn("font-medium", good ? "text-success-600 dark:text-success-400" : "text-error-600 dark:text-error-400")}>
                  {delta > 0 ? "▲" : "▼"} {Math.abs(delta).toLocaleString("es-CO")}
                  {deltaInPoints ? " pts" : ""}
                </span>{" "}
                vs. periodo anterior
              </>
            )}
          </p>
        </>
      )}
    </Card>
  );
}

// ---- Charts ----------------------------------------------------------------------

function DailyChart({ stats, loading }: { stats?: Stats; loading: boolean }) {
  const t = useChartTokens();
  const daily = stats?.daily ?? [];
  const hasData = daily.some((d) => d.completed + d.missed > 0);

  const options: ApexOptions = {
    chart: { type: "bar", stacked: true, toolbar: { show: false }, fontFamily: FONT_FAMILY, background: "transparent", animations: { speed: 250 } },
    theme: { mode: t.mode },
    colors: [t.attended, t.missed],
    plotOptions: {
      bar: {
        columnWidth: daily.length > 45 ? "70%" : "18px", // thin marks, never filling the slot
        borderRadius: 4,
        borderRadiusApplication: "end", // rounded data-end, square at the baseline
        borderRadiusWhenStacked: "last",
      },
    },
    stroke: { show: true, width: 2, colors: [t.surface] }, // 2px surface gap between stacked segments
    dataLabels: { enabled: false },
    legend: { show: true, position: "top", horizontalAlign: "left", fontSize: "13px", labels: { colors: t.axis }, markers: { size: 6, shape: "square" } },
    grid: { borderColor: t.grid, strokeDashArray: 0, xaxis: { lines: { show: false } } },
    xaxis: {
      categories: daily.map((d) => shortDate(d.date)),
      tickAmount: Math.min(daily.length, 10),
      labels: { style: { colors: t.axis, fontSize: "12px" }, rotate: 0, hideOverlappingLabels: true },
      axisBorder: { show: false },
      axisTicks: { show: false },
    },
    yaxis: { labels: { style: { colors: t.axis, fontSize: "12px" }, formatter: (v) => Math.round(v).toLocaleString("es-CO") }, forceNiceScale: true, min: 0 },
    tooltip: { shared: true, intersect: false, theme: t.mode, y: { formatter: (v) => `${v} ${v === 1 ? "cita" : "citas"}` } },
    states: { hover: { filter: { type: "lighten" } } },
  };

  return (
    <ChartCard
      className="xl:col-span-2"
      title="Citas por día"
      description="Atendidas frente a perdidas (no asistió o canceladas)."
      table={
        <ChartTable
          caption="Citas por día"
          columns={["Día", "Atendidas", "Perdidas"]}
          rows={daily.map((d) => [shortDate(d.date), d.completed, d.missed])}
        />
      }
    >
      <div className="h-72 px-3 pb-3">
        {loading ? (
          <Skeleton className="h-full w-full" />
        ) : !hasData ? (
          <EmptyState title="Aún no hay citas cerradas en este periodo." description="Cuando marques citas como atendidas o perdidas aparecerán aquí." />
        ) : (
          <Chart
            type="bar"
            height="100%"
            options={options}
            series={[
              { name: "Atendidas", data: daily.map((d) => d.completed) },
              { name: "Perdidas", data: daily.map((d) => d.missed) },
            ]}
          />
        )}
      </div>
    </ChartCard>
  );
}

function ServicesChart({ stats, loading }: { stats?: Stats; loading: boolean }) {
  const t = useChartTokens();
  const services = stats?.top_services ?? [];

  const options: ApexOptions = {
    chart: { type: "bar", toolbar: { show: false }, fontFamily: FONT_FAMILY, background: "transparent", animations: { speed: 250 } },
    theme: { mode: t.mode },
    colors: [t.single], // single series: one hue, no legend (the title names it)
    plotOptions: { bar: { horizontal: true, barHeight: "16px", borderRadius: 4, borderRadiusApplication: "end", dataLabels: { position: "top" } } },
    // Value at the bar tip, in a text color (never the series color).
    dataLabels: { enabled: true, offsetX: 22, style: { colors: [t.text], fontSize: "12px", fontWeight: 600 } },
    legend: { show: false },
    grid: { borderColor: t.grid, strokeDashArray: 0, yaxis: { lines: { show: false } }, padding: { right: 24 } },
    xaxis: { categories: services.map((s) => s.name), labels: { show: false }, axisBorder: { show: false }, axisTicks: { show: false } },
    yaxis: { labels: { style: { colors: t.axis, fontSize: "12px" }, maxWidth: 140 } },
    tooltip: { theme: t.mode, y: { formatter: (v) => `${v} ${v === 1 ? "cita" : "citas"}`, title: { formatter: () => "" } } },
    states: { hover: { filter: { type: "lighten" } } },
  };

  return (
    <ChartCard
      title="Servicios más agendados"
      description="Sin contar cancelaciones."
      table={<ChartTable caption="Servicios más agendados" columns={["Servicio", "Citas"]} rows={services.map((s) => [s.name, s.total])} />}
    >
      <div className="h-72 px-3 pb-3">
        {loading ? (
          <Skeleton className="h-full w-full" />
        ) : services.length === 0 ? (
          <EmptyState title="Sin servicios agendados en este periodo." />
        ) : (
          <Chart type="bar" height="100%" options={options} series={[{ name: "Citas", data: services.map((s) => s.total) }]} />
        )}
      </div>
    </ChartCard>
  );
}
