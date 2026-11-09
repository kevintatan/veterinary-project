"use client";

import SignHere from "@/components/domain/SignHere";
import { useRouter } from "next/navigation";
import { useState } from "react";

/** Sign from the portal: the session already proves who the owner is (no ID digits). */
export default function PortalSign({ id }: { id: string }) {
  const router = useRouter();
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  return (
    <section className="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900">
      <SignHere
        busy={busy}
        error={error}
        onSign={async (image) => {
          setBusy(true);
          setError(null);
          const response = await fetch(`/api/tutor/portal/documents/${id}/sign`, {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ image, accepted: true }),
          });
          const data = await response.json().catch(() => ({}));
          setBusy(false);
          if (!response.ok) {
            setError(data.errors?.image?.[0] ?? data.message ?? "No fue posible firmar. Intenta de nuevo.");
            return;
          }
          router.refresh();
        }}
      />
    </section>
  );
}
