"use client";

import { LogoMark } from "@/components/brand/Logo";
import { ThemeToggleButton } from "@/components/common/ThemeToggleButton";
import NotificationBell from "@/components/header/NotificationBell";
import UserMenu from "@/components/header/UserMenu";
import { useSession } from "@/components/providers/SessionProvider";
import { useSidebar } from "@/context/SidebarContext";
import type { Me } from "@/lib/types";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { useEffect, useRef } from "react";

export default function AppHeader() {
  const { isMobileOpen, toggleSidebar, toggleMobileSidebar } = useSidebar();
  const { subscription } = useSession();
  const router = useRouter();
  const searchRef = useRef<HTMLInputElement>(null);

  const toggle = () => (window.innerWidth >= 1280 ? toggleSidebar() : toggleMobileSidebar());

  // ⌘K / Ctrl+K: search a tutor or pet from anywhere.
  useEffect(() => {
    const onKeyDown = (event: KeyboardEvent) => {
      if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === "k") {
        event.preventDefault();
        searchRef.current?.focus();
      }
    };
    document.addEventListener("keydown", onKeyDown);
    return () => document.removeEventListener("keydown", onKeyDown);
  }, []);

  const onSearch = (event: React.FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    const q = searchRef.current?.value.trim();
    if (q) {
      router.push(`/app/tutores?q=${encodeURIComponent(q)}`);
    }
  };

  return (
    <header className="sticky top-0 z-40 flex w-full border-b border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900 print:hidden">
      <div className="flex w-full items-center gap-3 px-3 py-3 xl:px-6 xl:py-4">
        <button
          type="button"
          onClick={toggle}
          aria-label={isMobileOpen ? "Cerrar menú" : "Abrir o cerrar menú"}
          className="flex h-10 w-10 items-center justify-center rounded-lg border border-gray-200 text-gray-500 lg:h-11 lg:w-11 dark:border-gray-800 dark:text-gray-400"
        >
          <svg width="16" height="12" viewBox="0 0 16 12" fill="none" aria-hidden>
            <path d="M.58 1a.75.75 0 0 1 .75-.75h13.34a.75.75 0 0 1 0 1.5H1.33A.75.75 0 0 1 .58 1Zm0 10a.75.75 0 0 1 .75-.75h13.34a.75.75 0 0 1 0 1.5H1.33a.75.75 0 0 1-.75-.75Zm.75-5.75a.75.75 0 0 0 0 1.5H8a.75.75 0 0 0 0-1.5H1.33Z" fill="currentColor" />
          </svg>
        </button>

        <LogoMark className="xl:hidden" />

        <form onSubmit={onSearch} role="search" className="relative hidden flex-1 md:block md:max-w-107.5">
          <svg className="pointer-events-none absolute start-4 top-1/2 -translate-y-1/2 fill-gray-500" width="20" height="20" viewBox="0 0 20 20" aria-hidden>
            <path fillRule="evenodd" clipRule="evenodd" d="M3.04 9.37a6.33 6.33 0 1 1 12.67 0 6.33 6.33 0 0 1-12.67 0Zm6.34-7.83a7.83 7.83 0 1 0 4.98 13.88l2.82 2.82a.75.75 0 1 0 1.06-1.06l-2.82-2.82A7.83 7.83 0 0 0 9.38 1.54Z" />
          </svg>
          <input
            ref={searchRef}
            type="search"
            aria-label="Buscar tutor o mascota"
            placeholder="Buscar tutor, mascota, documento o celular…"
            className="h-11 w-full rounded-lg border border-gray-200 bg-transparent py-2.5 ps-12 pe-16 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 focus:outline-hidden dark:border-gray-800 dark:bg-white/3 dark:text-white/90 dark:placeholder:text-white/30"
          />
          <kbd className="absolute end-2.5 top-1/2 -translate-y-1/2 rounded-lg border border-gray-200 bg-gray-50 px-1.75 py-[4.5px] text-xs text-gray-500 dark:border-gray-800 dark:bg-white/3 dark:text-gray-400">
            Ctrl K
          </kbd>
        </form>

        <div className="ms-auto flex items-center gap-3">
          <PlanBadge subscription={subscription} />
          <ThemeToggleButton />
          <NotificationBell />
          <UserMenu />
        </div>
      </div>
    </header>
  );
}

/** Trial days, pending payment or read-only: always one click from Plan y facturación. */
function PlanBadge({ subscription }: { subscription: Me["subscription"] }) {
  const href = "/app/configuracion?seccion=plan";
  const base = "rounded-full px-3 py-1 text-xs font-medium whitespace-nowrap";

  if (!subscription || !subscription.allows_writes) {
    return (
      <Link href={href} className={`${base} bg-error-50 text-error-700 hover:bg-error-100 dark:bg-error-500/15 dark:text-error-300`}>
        Modo consulta · Activar plan
      </Link>
    );
  }
  if (subscription.status === "past_due") {
    return (
      <Link href={href} className={`${base} bg-warning-50 text-warning-700 hover:bg-warning-100 dark:bg-warning-500/15 dark:text-warning-300`}>
        Pago pendiente
      </Link>
    );
  }
  if (subscription.status === "trialing" && subscription.trial_days_left !== null) {
    return (
      <Link href={href} className={`${base} hidden bg-accent-50 text-accent-700 hover:bg-accent-100 sm:inline dark:bg-accent-500/15 dark:text-accent-300`}>
        Prueba gratis: {subscription.trial_days_left} {subscription.trial_days_left === 1 ? "día" : "días"}
      </Link>
    );
  }
  return null;
}
