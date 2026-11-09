"use client";

import { useSession } from "@/components/providers/SessionProvider";
import { Dropdown } from "@/components/ui/dropdown/Dropdown";
import { api } from "@/lib/api";
import type { ClinicOption } from "@/lib/types";
import { useQuery } from "@tanstack/react-query";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { useState } from "react";

type MyClinic = ClinicOption & { is_current: boolean };

export default function UserMenu() {
  const { user, clinic, role } = useSession();
  const router = useRouter();
  const [open, setOpen] = useState(false);
  const [busy, setBusy] = useState(false);

  const { data: clinics } = useQuery({
    queryKey: ["me", "clinics"],
    queryFn: () => api<{ data: MyClinic[] }>("me/clinics").then((r) => r.data),
    enabled: open,
    staleTime: 5 * 60_000,
  });
  const others = (clinics ?? []).filter((c) => !c.is_current);

  const initials = user.name
    .split(" ")
    .slice(0, 2)
    .map((part) => part[0])
    .join("")
    .toUpperCase();

  const switchTo = async (clinicId: string) => {
    setBusy(true);
    const response = await fetch("/api/auth/select-clinic", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ clinic_id: clinicId }),
    });
    // A new clinic means a new tenant: reload everything from scratch.
    window.location.assign(response.ok ? "/app" : "/login?reason=expired");
  };

  const logout = async () => {
    setBusy(true);
    await fetch("/api/auth/logout", { method: "POST" });
    router.replace("/login");
    router.refresh();
  };

  return (
    <div className="relative">
      <button
        type="button"
        onClick={() => setOpen((v) => !v)}
        aria-expanded={open}
        className="dropdown-toggle flex items-center gap-3 text-gray-700 dark:text-gray-400"
      >
        <span className="flex h-11 w-11 items-center justify-center rounded-full bg-brand-50 text-sm font-semibold text-brand-700 dark:bg-brand-500/15 dark:text-brand-300">
          {initials}
        </span>
        <span className="hidden text-left lg:block">
          <span className="block text-theme-sm font-medium text-gray-800 dark:text-white/90">{user.name}</span>
          <span className="block max-w-40 truncate text-theme-xs text-gray-500">{clinic.name}</span>
        </span>
      </button>

      <Dropdown
        isOpen={open}
        onClose={() => setOpen(false)}
        className="absolute end-0 mt-4 flex w-72 flex-col rounded-2xl border border-gray-200 bg-white p-3 shadow-theme-lg dark:border-gray-800 dark:bg-gray-dark"
      >
        <div className="border-b border-gray-200 px-2 pb-3 dark:border-gray-800">
          <p className="text-theme-sm font-medium text-gray-800 dark:text-white/90">{user.name}</p>
          <p className="text-theme-xs text-gray-500">{user.email}</p>
          <p className="mt-2 text-theme-xs text-gray-500">
            {role?.label ?? "Sin rol"} · <span className="font-medium text-gray-700 dark:text-gray-300">{clinic.name}</span>
          </p>
        </div>

        {others.length > 0 && (
          <div className="border-b border-gray-200 py-2 dark:border-gray-800">
            <p className="px-2 pb-1 text-theme-xs text-gray-400 uppercase">Cambiar de clínica</p>
            {others.map((other) => (
              <button
                key={other.id}
                type="button"
                disabled={busy}
                onClick={() => switchTo(other.id)}
                className="w-full rounded-lg px-2 py-2 text-left text-theme-sm text-gray-700 hover:bg-gray-100 disabled:opacity-60 dark:text-gray-300 dark:hover:bg-white/5"
              >
                {other.name}
              </button>
            ))}
          </div>
        )}

        <Link
          href="/app/configuracion?seccion=perfil"
          onClick={() => setOpen(false)}
          className="mt-2 rounded-lg px-2 py-2 text-theme-sm text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-white/5"
        >
          Mi perfil y contraseña
        </Link>
        <button
          type="button"
          disabled={busy}
          onClick={logout}
          className="rounded-lg px-2 py-2 text-left text-theme-sm font-medium text-gray-700 hover:bg-gray-100 disabled:opacity-60 dark:text-gray-300 dark:hover:bg-white/5"
        >
          Cerrar sesión
        </button>
      </Dropdown>
    </div>
  );
}
