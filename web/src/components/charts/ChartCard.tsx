"use client";

import { Card, CardHeader } from "@/components/common/Card";
import { useState } from "react";

/** A chart with its title and a "Ver tabla" toggle (every value reachable without hovering). */
export default function ChartCard({
  title,
  description,
  className,
  table,
  children,
}: {
  title: string;
  description: string;
  className?: string;
  table: React.ReactNode;
  children: React.ReactNode;
}) {
  const [asTable, setAsTable] = useState(false);

  return (
    <Card className={className}>
      <CardHeader
        title={title}
        description={description}
        action={
          <button
            type="button"
            onClick={() => setAsTable((v) => !v)}
            className="shrink-0 rounded-md px-2 py-1 text-theme-xs font-medium text-gray-500 hover:bg-gray-100 hover:text-gray-800 dark:hover:bg-white/5 dark:hover:text-gray-200"
          >
            {asTable ? "Ver gráfico" : "Ver tabla"}
          </button>
        }
      />
      {asTable ? table : children}
    </Card>
  );
}

/** "22 sept" for compact axes (dates come as clinic-local YYYY-MM-DD). */
export function shortDate(iso: string): string {
  const parts = new Intl.DateTimeFormat("es-CO", { day: "numeric", month: "short", timeZone: "UTC" }).formatToParts(new Date(`${iso}T00:00:00Z`));
  const day = parts.find((p) => p.type === "day")?.value ?? "";
  const month = (parts.find((p) => p.type === "month")?.value ?? "").replace(".", "");

  return `${day} ${month}`;
}

