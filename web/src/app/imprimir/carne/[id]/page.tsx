import type { Metadata } from "next";
import { Suspense } from "react";
import PrintCard from "./PrintCard";

export const metadata: Metadata = { title: "Imprimir carné", robots: { index: false } };

// Outside the dashboard layout on purpose: a clean sheet for the printer.
export default async function Page({ params }: { params: Promise<{ id: string }> }) {
  const { id } = await params;

  return (
    <Suspense>
      <PrintCard petId={id} />
    </Suspense>
  );
}
