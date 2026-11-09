import { Logo } from "@/components/brand/Logo";
import type { Metadata } from "next";
import Link from "next/link";
import { LoginForm } from "../PortalForm";

export const metadata: Metadata = { title: "Ingresar" };

/** Only internal paths after login (no open redirect). */
function safeNext(next: string | undefined): string {
  return next && next.startsWith("/portal") && !next.startsWith("//") ? next : "/portal";
}

export default async function Page({ searchParams }: { searchParams: Promise<{ next?: string; motivo?: string }> }) {
  const { next, motivo } = await searchParams;

  return (
    <main className="mx-auto flex min-h-screen max-w-sm flex-col justify-center px-4 py-10">
      <Logo className="mb-8 h-8 w-auto self-center" />
      <div className="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-gray-900">
        <h1 className="text-lg font-semibold">Mis mascotas</h1>
        <p className="mb-5 text-theme-sm text-gray-500">Entra con el correo y la contraseña que creaste al activar tu acceso.</p>
        <LoginForm next={safeNext(next)} notice={motivo === "expirada" ? "Tu sesión terminó. Ingresa de nuevo." : undefined} />
      </div>
      <Link href="/portal/recuperar" className="mt-4 self-center text-theme-sm font-medium text-brand-600 hover:underline">
        ¿Olvidaste tu contraseña?
      </Link>
      <p className="mt-6 text-center text-theme-xs text-gray-500">
        ¿Primera vez? Pídele a tu veterinaria el enlace de invitación: llega a tu WhatsApp o a tu correo.
      </p>
    </main>
  );
}
