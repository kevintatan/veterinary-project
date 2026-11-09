"use client";

import { Checkbox, Field, Input } from "@/components/form/fields";
import Button from "@/components/ui/button/Button";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { useState } from "react";

type Errors = Record<string, string[]>;

/**
 * New clinic: the clinic, the person who will administer it and their
 * access. Creates a 15-day trial with every Pro feature; no card needed.
 */
export default function RegisterForm() {
  const router = useRouter();
  const [errors, setErrors] = useState<Errors>({});
  const [message, setMessage] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  const [accepted, setAccepted] = useState(false);
  const [showPassword, setShowPassword] = useState(false);
  const err = (field: string) => errors[field]?.[0];

  return (
    <div>
      <div className="mb-6">
        <h1 className="mb-2 text-title-sm font-semibold text-gray-800 dark:text-white/90">Crea la cuenta de tu clínica</h1>
        <p className="text-sm text-gray-500 dark:text-gray-400">15 días gratis con todas las funciones. Sin tarjeta.</p>
      </div>

      {message && (
        <div role="alert" className="mb-5 rounded-lg border border-error-200 bg-error-50 px-4 py-3 text-sm text-error-700 dark:border-error-500/30 dark:bg-error-500/10 dark:text-error-400">
          {message}
        </div>
      )}

      <form
        noValidate
        className="space-y-4"
        onSubmit={async (e) => {
          e.preventDefault();
          const form = new FormData(e.currentTarget);
          setBusy(true);
          setErrors({});
          setMessage(null);
          const response = await fetch("/api/auth/register", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({
              clinic_name: form.get("clinic_name"),
              name: form.get("name"),
              email: form.get("email"),
              phone: form.get("phone"),
              password: form.get("password"),
              password_confirmation: form.get("password_confirmation"),
              accept_terms: accepted,
            }),
          });
          const data = await response.json().catch(() => ({}));
          setBusy(false);
          if (response.status === 429) {
            setMessage("Demasiados registros desde esta conexión. Intenta de nuevo en un rato.");
            return;
          }
          if (!response.ok) {
            setErrors(data.errors ?? {});
            if (!data.errors) setMessage(data.message ?? "No fue posible crear la cuenta.");
            return;
          }
          router.replace("/app?bienvenida=1");
          router.refresh();
        }}
      >
        <Field label="Nombre de la clínica" htmlFor="clinic_name" error={err("clinic_name")}>
          <Input id="clinic_name" name="clinic_name" autoComplete="organization" placeholder="Ej.: Veterinaria Huellitas" invalid={!!err("clinic_name")} autoFocus />
        </Field>
        <div className="grid gap-4 sm:grid-cols-2">
          <Field label="Tu nombre" htmlFor="name" error={err("name")}>
            <Input id="name" name="name" autoComplete="name" invalid={!!err("name")} />
          </Field>
          <Field label="Celular" htmlFor="phone" optional error={err("phone")}>
            <Input id="phone" name="phone" type="tel" autoComplete="tel" placeholder="300 123 4567" invalid={!!err("phone")} />
          </Field>
        </div>
        <Field label="Correo electrónico" htmlFor="email" hint="Con él ingresas a Zooetia." error={err("email")}>
          <Input id="email" name="email" type="email" autoComplete="email" placeholder="nombre@clinica.com" invalid={!!err("email")} />
        </Field>
        <div className="grid gap-4 sm:grid-cols-2">
          <Field label="Contraseña" htmlFor="password" hint="Mínimo 8, con letras y números." error={err("password")}>
            <Input id="password" name="password" type={showPassword ? "text" : "password"} autoComplete="new-password" invalid={!!err("password")} />
          </Field>
          <Field label="Repite la contraseña" htmlFor="password_confirmation">
            <Input id="password_confirmation" name="password_confirmation" type={showPassword ? "text" : "password"} autoComplete="new-password" />
          </Field>
        </div>
        <button type="button" onClick={() => setShowPassword((v) => !v)} className="text-theme-xs font-medium text-gray-500 hover:text-gray-800">
          {showPassword ? "Ocultar contraseñas" : "Mostrar contraseñas"}
        </button>

        <div>
          <Checkbox
            checked={accepted}
            onChange={(e) => setAccepted(e.target.checked)}
            label={
              <>
                Acepto los{" "}
                <Link href="/terminos" target="_blank" className="font-medium text-brand-600 underline">
                  términos de uso
                </Link>{" "}
                y la{" "}
                <Link href="/privacidad" target="_blank" className="font-medium text-brand-600 underline">
                  política de tratamiento de datos
                </Link>
                .
              </>
            }
          />
          {err("accept_terms") && <p className="mt-1 text-theme-xs text-error-500">{err("accept_terms")}</p>}
        </div>

        <Button type="submit" className="w-full" loading={busy} disabled={!accepted}>
          Crear cuenta y empezar
        </Button>
      </form>

      <p className="mt-5 text-center text-sm text-gray-500">
        ¿Ya tienes cuenta?{" "}
        <Link href="/login" className="font-medium text-brand-600 hover:underline">
          Ingresa
        </Link>
      </p>
    </div>
  );
}
