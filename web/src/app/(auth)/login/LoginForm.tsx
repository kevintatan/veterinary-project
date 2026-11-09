"use client";

import Input from "@/components/form/input/InputField";
import Label from "@/components/form/Label";
import Button from "@/components/ui/button/Button";
import { EyeCloseIcon, EyeIcon } from "@/icons";
import { zodResolver } from "@hookform/resolvers/zod";
import Link from "next/link";
import { useRouter, useSearchParams } from "next/navigation";
import { useState } from "react";
import { useForm } from "react-hook-form";
import { z } from "zod";

const schema = z.object({
  email: z.email("Escribe un correo válido."),
  password: z.string().min(1, "Escribe tu contraseña."),
});

type FormValues = z.infer<typeof schema>;

const REASONS: Record<string, string> = {
  expired: "Tu sesión terminó. Ingresa de nuevo.",
  forbidden: "Ya no tienes acceso a esa clínica. Ingresa de nuevo.",
};

export default function LoginForm() {
  const router = useRouter();
  const params = useSearchParams();
  const [showPassword, setShowPassword] = useState(false);
  const [serverError, setServerError] = useState<string | null>(REASONS[params.get("reason") ?? ""] ?? null);

  const {
    register,
    handleSubmit,
    formState: { errors, isSubmitting },
  } = useForm<FormValues>({ resolver: zodResolver(schema) });

  const onSubmit = async (values: FormValues) => {
    setServerError(null);

    const response = await fetch("/api/auth/login", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(values),
    });
    const data = await response.json().catch(() => ({}));

    if (response.status === 429) {
      setServerError("Demasiados intentos. Espera un minuto e intenta de nuevo.");
      return;
    }

    if (!response.ok) {
      setServerError(data.errors?.email?.[0] ?? data.message ?? "No fue posible ingresar.");
      return;
    }

    if (data.requires_clinic_selection) {
      router.push("/seleccionar-clinica");
      return;
    }

    router.replace(safeNext(params.get("next")));
    router.refresh();
  };

  return (
    <div>
      <div className="mb-8">
        <h1 className="mb-2 text-title-sm font-semibold text-gray-800 dark:text-white/90">Ingresar</h1>
        <p className="text-sm text-gray-500 dark:text-gray-400">Usa el correo y la contraseña de tu cuenta.</p>
      </div>

      {serverError && (
        <div role="alert" className="mb-6 rounded-lg border border-error-200 bg-error-50 px-4 py-3 text-sm text-error-700 dark:border-error-500/30 dark:bg-error-500/10 dark:text-error-400">
          {serverError}
        </div>
      )}

      <form onSubmit={handleSubmit(onSubmit)} noValidate className="space-y-5">
        <div>
          <Label htmlFor="email">Correo electrónico</Label>
          <Input
            id="email"
            type="email"
            autoComplete="email"
            placeholder="nombre@clinica.com"
            error={!!errors.email}
            hint={errors.email?.message}
            {...register("email")}
          />
        </div>

        <div>
          <Label htmlFor="password">Contraseña</Label>
          <div className="relative">
            <Input
              id="password"
              type={showPassword ? "text" : "password"}
              autoComplete="current-password"
              error={!!errors.password}
              hint={errors.password?.message}
              {...register("password")}
            />
            <button
              type="button"
              onClick={() => setShowPassword((v) => !v)}
              aria-label={showPassword ? "Ocultar contraseña" : "Mostrar contraseña"}
              className="absolute end-4 top-[22px] z-30 -translate-y-1/2"
            >
              {showPassword ? (
                <EyeIcon className="fill-gray-500 dark:fill-gray-400" />
              ) : (
                <EyeCloseIcon className="fill-gray-500 dark:fill-gray-400" />
              )}
            </button>
          </div>
        </div>

        <Button type="submit" className="w-full" loading={isSubmitting}>
          Ingresar
        </Button>
      </form>

      <p className="mt-6 text-center text-sm text-gray-500 dark:text-gray-400">
        ¿Tu clínica aún no usa Zooetia?{" "}
        <Link href="/registro" className="font-medium text-brand-600 hover:underline">
          Crea una cuenta gratis
        </Link>
      </p>
    </div>
  );
}

/** Only same-site paths inside the app (no open redirects). */
function safeNext(next: string | null): string {
  return next && (next.startsWith("/app") || /^\/invitacion\/[A-Za-z0-9]{48}$/.test(next)) && !next.startsWith("//") ? next : "/app";
}
