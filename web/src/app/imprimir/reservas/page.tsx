import type { Metadata } from "next";
import { Suspense } from "react";
import PrintPoster from "./PrintPoster";

export const metadata: Metadata = { title: "Cartel de reservas", robots: { index: false } };

// Outside the dashboard layout on purpose: a clean sheet for the printer.
export default function Page() {
  return (
    <Suspense>
      <PrintPoster />
    </Suspense>
  );
}
