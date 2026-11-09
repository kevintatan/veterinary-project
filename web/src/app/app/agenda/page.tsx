import type { Metadata } from "next";
import { Suspense } from "react";
import AgendaView from "./AgendaView";

export const metadata: Metadata = { title: "Agenda" };

// AgendaView reads the date/view from the URL (useSearchParams) → Suspense boundary.
export default function Page() {
  return (
    <Suspense>
      <AgendaView />
    </Suspense>
  );
}
