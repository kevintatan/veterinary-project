import type { Metadata } from "next";
import { Suspense } from "react";
import InventoryView from "./InventoryView";

export const metadata: Metadata = { title: "Inventario" };

// InventoryView reads the branch and tab from the URL (useSearchParams) → Suspense boundary.
export default function Page() {
  return (
    <Suspense>
      <InventoryView />
    </Suspense>
  );
}
