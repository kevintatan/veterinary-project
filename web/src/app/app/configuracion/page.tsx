import type { Metadata } from "next";
import { Suspense } from "react";
import SettingsView from "./SettingsView";

export const metadata: Metadata = { title: "Configuración" };

// The active section lives in the URL (?seccion=…) → Suspense for useSearchParams.
export default function Page() {
  return (
    <Suspense>
      <SettingsView />
    </Suspense>
  );
}
