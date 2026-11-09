"use client";

import { Field, Input } from "@/components/form/fields";
import Button from "@/components/ui/button/Button";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { useState } from "react";

export default function AcceptInvitation({ token, email, accountExists, signedIn }: { token: string; email: string; accountExists: boolean; signedIn: boolean }) {
  const router = useRouter();
  const [form, setForm] = useState({ name: "", password: "", password_confirmation: "" });
  const [pending, setPending] = useState(false);
  const [error, setError] = useState<{ message: string; errors: Record<string, string[]> } | null>(null);

  const accept = async (mode: "new" | "existing") => {
    setPending(true);
    setError(null);
    const response = await fetch("/api/auth/invitation", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ token, mode, ...(mode === "new" ? form : {}) }),
    });
    const data = await response.json().catch(() => ({}));

    if (!response.ok) {
      setPending(false);
      setError({ message: data.message ?? "No fue posible aceptar la invitación.", errors: data.errors ?? {} });
      return;
    }

    router.replace("/app");
    router.refresh();
  };

  if (accountExists) {
    return signedIn ? (
      <div className="space-y-4">
        <p className="text-theme-sm text-gray-600 dark:text-gray-400">Ya tienes cuenta. La clínica se agregará a tus clínicas y podrás cambiar entre ellas desde tu menú.</p>
        {error && <p className="text-theme-sm text-error-600">{error.message}</p>}
        <Button className="w-full" loading={pending} onClick={() => accept("existing")}>
          Aceptar invitación
        </Button>
        <p className="text-center text-theme-xs text-gray-500">Debes haber iniciado sesión como {email}.</p>
      </div>
    ) : (
      <div className="space-y-4">
        <p className="text-theme-sm text-gray-600 dark:text-gray-400">Ya tienes una cuenta en Zooetia con este correo. Inicia sesión para aceptar.</p>
        <Link
          href={`/login?next=${encodeURIComponent(`/invitacion/${token}`)}`}
          className="flex h-12 items-center justify-center rounded-lg bg-brand-500 text-sm font-medium text-white hover:bg-brand-600"
        >
          Iniciar sesión y aceptar
        </Link>
      </div>
    );
  }

  const fieldError = (name: string) => error?.errors[name]?.[0];

  return (
    <form
      className="space-y-5"
      onSubmit={(e) => {
        e.preventDefault();
        accept("new");
      }}
    >
      <Field label="Tu nombre completo" htmlFor="inv-name" error={fieldError("name")}>
        <Input id="inv-name" autoFocus required value={form.name} onChange={(e) => setForm((f) => ({ ...f, name: e.target.value }))} autoComplete="name" />
      </Field>
      <Field label="Crea una contraseña" htmlFor="inv-pass" hint="Mínimo 8 caracteres, con letras y números." error={fieldError("password")}>
        <Input id="inv-pass" type="password" required value={form.password} onChange={(e) => setForm((f) => ({ ...f, password: e.target.value }))} autoComplete="new-password" />
      </Field>
      <Field label="Repite la contraseña" htmlFor="inv-pass2">
        <Input id="inv-pass2" type="password" required value={form.password_confirmation} onChange={(e) => setForm((f) => ({ ...f, password_confirmation: e.target.value }))} autoComplete="new-password" />
      </Field>
      {error && Object.keys(error.errors).length === 0 && <p className="text-theme-sm text-error-600">{error.message}</p>}
      <Button type="submit" className="w-full" loading={pending}>
        Crear cuenta y entrar
      </Button>
    </form>
  );
}
