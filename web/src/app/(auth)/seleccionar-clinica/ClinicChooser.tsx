"use client";

import { LogoMark } from "@/components/brand/Logo";
import type { ClinicOption } from "@/lib/types";
import { useRouter } from "next/navigation";
import { useState } from "react";

export default function ClinicChooser({ clinics }: { clinics: ClinicOption[] }) {
  const router = useRouter();
  const [pending, setPending] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);

  const choose = async (clinicId: string) => {
    setPending(clinicId);
    setError(null);

    const response = await fetch("/api/auth/select-clinic", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ clinic_id: clinicId }),
    });

    if (response.status === 401) {
      router.replace("/login?reason=expired");
      return;
    }

    if (!response.ok) {
      const data = await response.json().catch(() => ({}));
      setError(data.message ?? "No fue posible abrir la clínica.");
      setPending(null);
      return;
    }

    router.replace("/app");
    router.refresh();
  };

  return (
    <>
      {error && (
        <p role="alert" className="mb-4 text-sm text-error-600">
          {error}
        </p>
      )}
      <ul className="space-y-3">
        {clinics.map((clinic) => (
          <li key={clinic.id}>
            <button
              type="button"
              disabled={pending !== null}
              onClick={() => choose(clinic.id)}
              className="flex w-full items-center gap-4 rounded-xl border border-gray-200 bg-white p-4 text-left transition hover:border-brand-300 hover:bg-brand-25 disabled:opacity-60 dark:border-gray-800 dark:bg-white/3 dark:hover:border-brand-700"
            >
              <LogoMark className="h-10 w-10" />
              <span className="flex-1 font-medium text-gray-800 dark:text-white/90">{clinic.name}</span>
              {pending === clinic.id && (
                <span className="h-4 w-4 animate-spin rounded-full border-2 border-brand-500 border-t-transparent" aria-hidden />
              )}
            </button>
          </li>
        ))}
      </ul>
    </>
  );
}
