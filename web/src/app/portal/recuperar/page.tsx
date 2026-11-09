import { Logo } from "@/components/brand/Logo";
import type { Metadata } from "next";
import Link from "next/link";
import { ResetForm } from "../PortalForm";

export const metadata: Metadata = { title: "Recuperar contraseña" };

export default function Page() {
  return (
    <main className="mx-auto flex min-h-screen max-w-sm flex-col justify-center px-4 py-10">
      <Logo className="mb-8 h-8 w-auto self-center" />
      <div className="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-gray-900">
        <h1 className="text-lg font-semibold">Recupera tu acceso</h1>
        <p className="mb-5 text-theme-sm text-gray-500">Te enviamos un código a tu correo para crear una contraseña nueva.</p>
        <ResetForm />
      </div>
      <Link href="/portal/ingresar" className="mt-4 self-center text-theme-sm font-medium text-gray-500 hover:text-gray-800">
        ← Volver a ingresar
      </Link>
    </main>
  );
}
