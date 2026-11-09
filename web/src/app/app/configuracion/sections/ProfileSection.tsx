"use client";

import { Card, CardHeader } from "@/components/common/Card";
import { Field, Input } from "@/components/form/fields";
import { useSession } from "@/components/providers/SessionProvider";
import Button from "@/components/ui/button/Button";
import { api, ApiError } from "@/lib/api";
import { fromE164, toE164 } from "@/lib/patients";
import { useMutation } from "@tanstack/react-query";
import { useRouter } from "next/navigation";
import { useState } from "react";

export default function ProfileSection() {
  return (
    <div className="space-y-6">
      <ProfileForm />
      <PasswordForm />
    </div>
  );
}

function ProfileForm() {
  const { user } = useSession();
  const router = useRouter();
  const initial = { name: user.name, phone: fromE164(user.phone), professional_license: user.professional_license ?? "" };
  const [form, setForm] = useState(initial);
  const dirty = JSON.stringify(form) !== JSON.stringify(initial);

  const save = useMutation({
    mutationFn: () => api("me", { method: "PATCH", body: { name: form.name.trim(), phone: toE164(form.phone), professional_license: form.professional_license.trim() || null } }),
    onSuccess: () => router.refresh(), // the session (header, signatures) comes from the server
  });
  const error = save.error instanceof ApiError ? save.error : null;

  return (
    <Card>
      <CardHeader title="Mis datos" description={`${user.email} · tus datos son los mismos en todas tus clínicas.`} />
      <form
        className="grid gap-4 p-5 sm:grid-cols-2"
        onSubmit={(e) => {
          e.preventDefault();
          save.mutate();
        }}
      >
        <Field label="Nombre completo" htmlFor="pf-name" error={error?.field("name")} className="sm:col-span-2">
          <Input id="pf-name" value={form.name} onChange={(e) => setForm((f) => ({ ...f, name: e.target.value }))} />
        </Field>
        <Field label="Celular" htmlFor="pf-phone" optional error={error?.field("phone")}>
          <Input id="pf-phone" type="tel" value={form.phone} onChange={(e) => setForm((f) => ({ ...f, phone: e.target.value }))} placeholder="300 111 2233" />
        </Field>
        <Field label="Tarjeta profesional" htmlFor="pf-license" optional hint="Sale junto a tu firma en historias clínicas y fórmulas." error={error?.field("professional_license")}>
          <Input id="pf-license" value={form.professional_license} onChange={(e) => setForm((f) => ({ ...f, professional_license: e.target.value }))} placeholder="COMVEZCOL 12345" />
        </Field>
        <div className="flex items-center justify-end gap-3 sm:col-span-2">
          {save.isSuccess && !dirty && <span className="text-theme-xs text-success-600">Guardado</span>}
          <Button type="submit" size="sm" disabled={!dirty || form.name.trim().length < 3} loading={save.isPending}>
            Guardar cambios
          </Button>
        </div>
      </form>
    </Card>
  );
}

function PasswordForm() {
  const [form, setForm] = useState({ current_password: "", password: "", password_confirmation: "" });

  const save = useMutation({
    mutationFn: () => api("me/password", { method: "PUT", body: form }),
    onSuccess: () => setForm({ current_password: "", password: "", password_confirmation: "" }),
  });
  const error = save.error instanceof ApiError ? save.error : null;
  const mismatch = form.password_confirmation !== "" && form.password !== form.password_confirmation;

  return (
    <Card>
      <CardHeader title="Contraseña" description="Al cambiarla se cierran tus sesiones en otros dispositivos." />
      <form
        className="grid gap-4 p-5 sm:grid-cols-3"
        onSubmit={(e) => {
          e.preventDefault();
          save.mutate();
        }}
      >
        <Field label="Contraseña actual" htmlFor="pw-current" error={error?.field("current_password")}>
          <Input id="pw-current" type="password" autoComplete="current-password" value={form.current_password} onChange={(e) => setForm((f) => ({ ...f, current_password: e.target.value }))} />
        </Field>
        <Field label="Nueva contraseña" htmlFor="pw-new" hint="Mínimo 8, con letras y números." error={error?.field("password")}>
          <Input id="pw-new" type="password" autoComplete="new-password" value={form.password} onChange={(e) => setForm((f) => ({ ...f, password: e.target.value }))} />
        </Field>
        <Field label="Repítela" htmlFor="pw-confirm" error={mismatch ? "No coincide." : undefined}>
          <Input id="pw-confirm" type="password" autoComplete="new-password" value={form.password_confirmation} onChange={(e) => setForm((f) => ({ ...f, password_confirmation: e.target.value }))} />
        </Field>
        <div className="flex items-center justify-end gap-3 sm:col-span-3">
          {save.isSuccess && <span className="text-theme-xs text-success-600">Contraseña actualizada</span>}
          <Button type="submit" size="sm" disabled={!form.current_password || form.password.length < 8 || mismatch || !form.password_confirmation} loading={save.isPending}>
            Cambiar contraseña
          </Button>
        </div>
      </form>
    </Card>
  );
}
