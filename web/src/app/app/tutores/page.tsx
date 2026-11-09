import type { Metadata } from "next";
import { Suspense } from "react";
import OwnersList from "./OwnersList";

export const metadata: Metadata = { title: "Tutores y mascotas" };

// OwnersList reads the search from the URL (useSearchParams) → Suspense boundary.
export default function Page() {
  return (
    <Suspense>
      <OwnersList />
    </Suspense>
  );
}
