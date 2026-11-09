"use client";

import { Field, Input } from "@/components/form/fields";
import { useRouter } from "next/navigation";
import { useState } from "react";

type Errors = Record<string, string[]>;

async function tutorAuth(body: Record<string, unknown>): Promise<{ ok: boolean; status: number; message?: string; errors?: Errors }> {
  const response = await fetch("/api/tutor-auth", { method: "POST", headers: { "Content-Type": "application/json" }, body: JSON.stringify(body) });
  const data = await response.json().catch(() => ({}));
  return { ok: response.ok, status: response.status, message: data.message, errors: data.errors };
}

const tooMany = "Demasiados intentos. Espera un minuto e intenta de nuevo.";

/** Sign in with email + password (no codes: the account was verified once). */
export function LoginForm({ next, notice }: { next: string; notice?: string }) {
  const router = useRouter();
  const [error, setError] = useState<string | null>(notice ?? null);
  const [busy, setBusy] = useState(false);

  return (
    <form
      className="space-y-4"
      onSubmit={async (e) => {
        e.preventDefault();
        const form = new FormData(e.currentTarget);
        setBusy(true);
        setError(null);
        const result = await tutorAuth({ action: "login", email: form.get("email"), password: form.get("password") });
        setBusy(false);
        if (!result.ok) {
          setError(result.status === 429 ? tooMany : (result.errors?.email?.[0] ?? result.message ?? "No fue posible ingresar."));
          return;
        }
        router.replace(next);
        router.refresh();
      }}
    >
      {error && <Alert>{error}</Alert>}
      <Field label="Correo" htmlFor="email">
        <Input id="email" name="email" type="email" autoComplete="email" required />
      </Field>
      <Field label="Contraseña" htmlFor="password">
        <Input id="password" name="password" type="password" autoComplete="current-password" required />
      </Field>
      <Submit busy={busy}>Ingresar</Submit>
    </form>
  );
}

/** First time: create the access from the clinic's invitation. */
export function ActivateForm({ token, name, email }: { token: string; name: string; email: string | null }) {
  const router = useRouter();
  const [errors, setErrors] = useState<Errors>({});
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  return (
    <form
      className="space-y-4"
      onSubmit={async (e) => {
        e.preventDefault();
        const form = new FormData(e.currentTarget);
        setBusy(true);
        setError(null);
        setErrors({});
        const result = await tutorAuth({
          action: "activate",
          token,
          name: form.get("name"),
          email: form.get("email"),
          password: form.get("password"),
          password_confirmation: form.get("password_confirmation"),
        });
        setBusy(false);
        if (!result.ok) {
          setErrors(result.errors ?? {});
          if (!result.errors) setError(result.status === 429 ? tooMany : (result.message ?? "No fue posible activar tu acceso."));
          return;
        }
        router.replace("/portal");
        router.refresh();
      }}
    >
      {error && <Alert>{error}</Alert>}
      <Field label="Tu nombre" htmlFor="name" error={errors.name?.[0]}>
        <Input id="name" name="name" defaultValue={name} autoComplete="name" required invalid={!!errors.name} />
      </Field>
      <Field label="Correo" htmlFor="email" hint="Con él entras al portal." error={errors.email?.[0]}>
        <Input id="email" name="email" type="email" defaultValue={email ?? ""} autoComplete="email" required invalid={!!errors.email} />
      </Field>
      <Field label="Crea una contraseña" htmlFor="password" hint="Mínimo 8 caracteres, con letras y números." error={errors.password?.[0]}>
        <Input id="password" name="password" type="password" autoComplete="new-password" required invalid={!!errors.password} />
      </Field>
      <Field label="Repite la contraseña" htmlFor="password_confirmation">
        <Input id="password_confirmation" name="password_confirmation" type="password" autoComplete="new-password" required />
      </Field>
      <Submit busy={busy}>Activar mi acceso</Submit>
    </form>
  );
}

/** Already has an account (signed in): add this clinic with one tap. */
export function LinkButton({ token, clinic }: { token: string; clinic: string }) {
  const router = useRouter();
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  return (
    <div className="space-y-3">
      {error && <Alert>{error}</Alert>}
      <button
        type="button"
        disabled={busy}
        onClick={async () => {
          setBusy(true);
          const result = await tutorAuth({ action: "link", token });
          setBusy(false);
          if (!result.ok) {
            setError(result.errors?.token?.[0] ?? result.message ?? "No fue posible agregar la veterinaria.");
            return;
          }
          router.replace("/portal");
          router.refresh();
        }}
        className="w-full rounded-xl bg-brand-500 px-4 py-3 text-sm font-semibold text-white hover:bg-brand-600 disabled:opacity-60"
      >
        {busy ? "Agregando…" : `Agregar ${clinic} a mi cuenta`}
      </button>
    </div>
  );
}

/**
 * Forgotten password, two steps on the same screen: email → code (by email,
 * 15 min) + new password. Ends signed in.
 */
export function ResetForm() {
  const router = useRouter();
  const [email, setEmail] = useState<string | null>(null);
  const [sent, setSent] = useState<string | null>(null);
  const [errors, setErrors] = useState<Errors>({});
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  if (email === null) {
    return (
      <form
        className="space-y-4"
        onSubmit={async (e) => {
          e.preventDefault();
          const value = String(new FormData(e.currentTarget).get("email") ?? "");
          setBusy(true);
          setError(null);
          const result = await tutorAuth({ action: "forgot", email: value });
          setBusy(false);
          if (!result.ok) {
            setError(result.status === 429 ? tooMany : (result.errors?.email?.[0] ?? result.message ?? "No fue posible enviar el código."));
            return;
          }
          setSent(result.message ?? null);
          setEmail(value);
        }}
      >
        {error && <Alert>{error}</Alert>}
        <Field label="Correo de tu cuenta" htmlFor="email" hint="Te enviaremos un código de 6 números.">
          <Input id="email" name="email" type="email" autoComplete="email" required />
        </Field>
        <Submit busy={busy}>Enviar código</Submit>
      </form>
    );
  }

  return (
    <form
      className="space-y-4"
      onSubmit={async (e) => {
        e.preventDefault();
        const form = new FormData(e.currentTarget);
        setBusy(true);
        setError(null);
        setErrors({});
        const result = await tutorAuth({
          action: "reset",
          email,
          code: String(form.get("code") ?? "").replace(/\D/g, ""),
          password: form.get("password"),
          password_confirmation: form.get("password_confirmation"),
        });
        setBusy(false);
        if (!result.ok) {
          setErrors(result.errors ?? {});
          if (!result.errors) setError(result.status === 429 ? tooMany : (result.message ?? "No fue posible cambiar la contraseña."));
          return;
        }
        router.replace("/portal");
        router.refresh();
      }}
    >
      <p className="rounded-lg bg-brand-25 px-4 py-3 text-theme-sm text-brand-800 dark:bg-brand-500/10 dark:text-brand-300">{sent ?? "Revisa tu correo."}</p>
      {error && <Alert>{error}</Alert>}
      <Field label="Código" htmlFor="code" error={errors.code?.[0]}>
        <Input id="code" name="code" inputMode="numeric" autoComplete="one-time-code" maxLength={6} required invalid={!!errors.code} className="tracking-[0.4em]" />
      </Field>
      <Field label="Contraseña nueva" htmlFor="password" hint="Mínimo 8 caracteres, con letras y números." error={errors.password?.[0]}>
        <Input id="password" name="password" type="password" autoComplete="new-password" required invalid={!!errors.password} />
      </Field>
      <Field label="Repite la contraseña" htmlFor="password_confirmation">
        <Input id="password_confirmation" name="password_confirmation" type="password" autoComplete="new-password" required />
      </Field>
      <Submit busy={busy}>Cambiar contraseña y entrar</Submit>
      <button type="button" onClick={() => setEmail(null)} className="w-full text-center text-theme-sm text-gray-500 hover:text-gray-800">
        ¿No te llegó? Pide otro código
      </button>
    </form>
  );
}

function Submit({ busy, children }: { busy: boolean; children: React.ReactNode }) {
  return (
    <button type="submit" disabled={busy} className="w-full rounded-xl bg-brand-500 px-4 py-3 text-sm font-semibold text-white hover:bg-brand-600 disabled:opacity-60">
      {busy ? "Un momento…" : children}
    </button>
  );
}

function Alert({ children }: { children: React.ReactNode }) {
  return (
    <p role="alert" className="rounded-lg border border-error-200 bg-error-50 px-4 py-3 text-theme-sm text-error-700 dark:border-error-500/30 dark:bg-error-500/10 dark:text-error-400">
      {children}
    </p>
  );
}
