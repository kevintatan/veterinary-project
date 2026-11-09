"use client";

import { Logo } from "@/components/brand/Logo";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { useState } from "react";

export default function PortalHeader({ name, back }: { name?: string; back?: { href: string; label: string } }) {
  const router = useRouter();
  const [leaving, setLeaving] = useState(false);

  const logout = async () => {
    setLeaving(true);
    await fetch("/api/tutor-auth", { method: "POST", headers: { "Content-Type": "application/json" }, body: JSON.stringify({ action: "logout" }) });
    router.replace("/portal/ingresar");
    router.refresh();
  };

  return (
    <header className="sticky top-0 z-10 border-b border-gray-200 bg-white/90 backdrop-blur dark:border-gray-800 dark:bg-gray-900/90">
      <div className="mx-auto flex h-14 max-w-md items-center justify-between px-4">
        {back ? (
          <Link href={back.href} className="flex items-center gap-1 text-theme-sm font-medium text-gray-700 dark:text-gray-300">
            <span aria-hidden>←</span> {back.label}
          </Link>
        ) : (
          <Link href="/portal" aria-label="Mis mascotas">
            <Logo className="h-7 w-auto" />
          </Link>
        )}
        <div className="flex items-center gap-3">
          {name && <span className="hidden text-theme-sm text-gray-500 sm:inline">{name}</span>}
          <button type="button" onClick={logout} disabled={leaving} className="text-theme-sm font-medium text-gray-500 hover:text-gray-800 dark:hover:text-white">
            Salir
          </button>
        </div>
      </div>
    </header>
  );
}
