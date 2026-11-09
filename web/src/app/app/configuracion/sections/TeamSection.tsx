"use client";

import { Card, CardHeader, EmptyState, Skeleton } from "@/components/common/Card";
import SidePanel from "@/components/common/SidePanel";
import { Checkbox, Field, Input } from "@/components/form/fields";
import { useCan, useSession } from "@/components/providers/SessionProvider";
import Button from "@/components/ui/button/Button";
import { api, ApiError } from "@/lib/api";
import { initials } from "@/lib/patients";
import { AGENDA_COLORS, type Branch, type Invitation, type Member, ROLES, type RoleName } from "@/lib/settings";
import { cn } from "@/utils";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useState } from "react";
import { Switch } from "./ClinicSection";

export default function TeamSection() {
  const canInvite = useCan("members.invite");
  const canManage = useCan("members.manage");
  const [editing, setEditing] = useState<Member | null>(null);
  const [inviting, setInviting] = useState(false);

  const members = useQuery({ queryKey: ["members"], queryFn: () => api<{ data: Member[] }>("members").then((r) => r.data) });

  return (
    <div className="space-y-6">
      <Card>
        <CardHeader
          title="Equipo"
          description="Quién entra a la clínica y qué puede hacer."
          action={
            canInvite && (
              <Button size="sm" onClick={() => setInviting(true)}>
                Invitar persona
              </Button>
            )
          }
        />
        {!members.data ? (
          <Skeleton className="m-5 h-32" />
        ) : (
          <ul className="divide-y divide-gray-100 dark:divide-gray-800">
            {members.data.map((m) => (
              <li key={m.id} className={cn("flex flex-wrap items-center gap-4 px-5 py-3.5", m.status === "disabled" && "opacity-60")}>
                <span
                  className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-theme-xs font-semibold text-white"
                  style={{ backgroundColor: m.agenda_color ?? "#98A2B3" }}
                  aria-hidden
                >
                  {initials(m.user.name)}
                </span>
                <div className="min-w-0 flex-1">
                  <p className="font-medium text-gray-800 dark:text-white/90">
                    {m.user.name}
                    {m.status === "disabled" && <span className="ms-2 rounded-full bg-gray-100 px-2 py-0.5 text-theme-xs text-gray-500 dark:bg-white/5">Sin acceso</span>}
                  </p>
                  <p className="truncate text-theme-xs text-gray-500">
                    {m.user.email}
                    {m.branches.length > 0 && ` · ${m.branches.map((b) => b.name).join(", ")}`}
                  </p>
                </div>
                <span className="rounded-full bg-gray-100 px-2.5 py-1 text-theme-xs font-medium text-gray-700 dark:bg-white/5 dark:text-gray-300">{m.role.label}</span>
                {m.shows_in_agenda && <span className="hidden text-theme-xs text-gray-500 sm:inline">En agenda</span>}
                {canManage && (
                  <Button size="sm" variant="outline" onClick={() => setEditing(m)}>
                    Editar
                  </Button>
                )}
              </li>
            ))}
          </ul>
        )}
      </Card>

      {canInvite && <PendingInvitations />}

      {editing && <MemberPanel member={editing} onClose={() => setEditing(null)} />}
      {inviting && <InvitePanel onClose={() => setInviting(false)} />}
    </div>
  );
}

function useBranches() {
  return useQuery({ queryKey: ["branches"], queryFn: () => api<{ data: Branch[] }>("branches").then((r) => r.data.filter((b) => b.is_active)) });
}

function RolePicker({ value, onChange }: { value: RoleName; onChange: (v: RoleName) => void }) {
  return (
    <div className="space-y-2" role="radiogroup" aria-label="Rol">
      {ROLES.map((r) => (
        <label
          key={r.value}
          className={cn(
            "flex cursor-pointer gap-3 rounded-xl border px-4 py-3",
            value === r.value ? "border-brand-500 bg-brand-50/60 dark:bg-brand-500/10" : "border-gray-200 hover:border-gray-300 dark:border-gray-800",
          )}
        >
          <input type="radio" name="role" className="mt-1 accent-brand-500" checked={value === r.value} onChange={() => onChange(r.value)} />
          <span>
            <span className="block text-theme-sm font-medium text-gray-800 dark:text-white/90">{r.label}</span>
            <span className="block text-theme-xs text-gray-500 dark:text-gray-400">{r.description}</span>
          </span>
        </label>
      ))}
    </div>
  );
}

function BranchPicker({ value, onChange }: { value: string[]; onChange: (v: string[]) => void }) {
  const branches = useBranches();
  return (
    <div className="space-y-2">
      {branches.data?.map((b) => (
        <Checkbox key={b.id} label={b.name} checked={value.includes(b.id)} onChange={(e) => onChange(e.target.checked ? [...value, b.id] : value.filter((id) => id !== b.id))} />
      ))}
    </div>
  );
}

function MemberPanel({ member, onClose }: { member: Member; onClose: () => void }) {
  const queryClient = useQueryClient();
  const { user } = useSession();
  const isMe = member.user.id === user.id;
  const [form, setForm] = useState({
    role: member.role.name,
    branch_ids: member.branches.map((b) => b.id),
    shows_in_agenda: member.shows_in_agenda,
    agenda_color: member.agenda_color ?? AGENDA_COLORS[0],
    status: member.status,
  });

  const save = useMutation({
    // Own role/status can't change (the API rejects it): don't send them.
    mutationFn: () => api(`members/${member.id}`, { method: "PATCH", body: isMe ? { branch_ids: form.branch_ids, shows_in_agenda: form.shows_in_agenda, agenda_color: form.agenda_color } : form }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["members"] });
      queryClient.invalidateQueries({ queryKey: ["agenda"] });
      onClose();
    },
  });
  const error = save.error instanceof ApiError ? (Object.values(save.error.errors)[0]?.[0] ?? save.error.message) : null;

  return (
    <SidePanel
      title={member.user.name}
      description={member.user.email}
      onClose={onClose}
      footer={
        <div className="flex items-center justify-between gap-3">
          <p className="min-w-0 text-theme-xs text-error-500">{error}</p>
          <div className="flex shrink-0 gap-3">
            <Button size="sm" variant="outline" onClick={onClose}>
              Cancelar
            </Button>
            <Button size="sm" disabled={form.branch_ids.length === 0} loading={save.isPending} onClick={() => save.mutate()}>
              Guardar
            </Button>
          </div>
        </div>
      }
    >
      <div className="space-y-6">
        <section>
          <h3 className="mb-2 text-theme-xs font-semibold tracking-wide text-gray-400 uppercase">Rol</h3>
          {isMe ? (
            <p className="rounded-lg bg-gray-50 px-3 py-2.5 text-theme-sm text-gray-600 dark:bg-white/3 dark:text-gray-400">
              {member.role.label}. No puedes cambiar tu propio rol; pídeselo a otro administrador.
            </p>
          ) : (
            <RolePicker value={form.role} onChange={(role) => setForm((f) => ({ ...f, role }))} />
          )}
        </section>
        <section>
          <h3 className="mb-2 text-theme-xs font-semibold tracking-wide text-gray-400 uppercase">Sedes donde trabaja</h3>
          <BranchPicker value={form.branch_ids} onChange={(branch_ids) => setForm((f) => ({ ...f, branch_ids }))} />
        </section>
        <section className="space-y-3">
          <h3 className="text-theme-xs font-semibold tracking-wide text-gray-400 uppercase">Agenda</h3>
          <div className="flex items-center justify-between gap-4">
            <div>
              <p className="text-theme-sm font-medium text-gray-800 dark:text-white/90">Aparece en la agenda</p>
              <p className="text-theme-xs text-gray-500">Tiene su propia columna y se le pueden asignar citas.</p>
            </div>
            <Switch checked={form.shows_in_agenda} label="Aparece en la agenda" onChange={(v) => setForm((f) => ({ ...f, shows_in_agenda: v }))} />
          </div>
          {form.shows_in_agenda && (
            <div>
              <p className="mb-2 text-theme-sm text-gray-700 dark:text-gray-300">Color</p>
              <div className="flex flex-wrap gap-2" role="radiogroup" aria-label="Color en la agenda">
                {AGENDA_COLORS.map((c) => (
                  <button
                    key={c}
                    type="button"
                    role="radio"
                    aria-checked={form.agenda_color === c}
                    aria-label={c}
                    onClick={() => setForm((f) => ({ ...f, agenda_color: c }))}
                    className={cn("h-8 w-8 rounded-full ring-offset-2 dark:ring-offset-gray-900", form.agenda_color === c && "ring-2 ring-gray-900 dark:ring-white")}
                    style={{ backgroundColor: c }}
                  />
                ))}
              </div>
            </div>
          )}
        </section>
        {!isMe && (
          <section className="rounded-xl border border-gray-200 p-4 dark:border-gray-800">
            <div className="flex items-center justify-between gap-4">
              <div>
                <p className="text-theme-sm font-medium text-gray-800 dark:text-white/90">Acceso a la clínica</p>
                <p className="text-theme-xs text-gray-500">Si lo quitas, no podrá entrar. Sus registros clínicos se conservan con su nombre.</p>
              </div>
              <Switch checked={form.status === "active"} label="Acceso a la clínica" onChange={(v) => setForm((f) => ({ ...f, status: v ? "active" : "disabled" }))} />
            </div>
          </section>
        )}
      </div>
    </SidePanel>
  );
}

function InvitePanel({ onClose }: { onClose: () => void }) {
  const queryClient = useQueryClient();
  const branches = useBranches();
  const [form, setForm] = useState<{ email: string; role: RoleName; branch_ids: string[] | null }>({ email: "", role: "veterinarian", branch_ids: null });
  const branchIds = form.branch_ids ?? branches.data?.map((b) => b.id) ?? [];

  const invite = useMutation({
    mutationFn: () => api<{ data: Invitation }>("invitations", { method: "POST", body: { ...form, email: form.email.trim(), branch_ids: branchIds } }),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ["invitations"] }),
  });
  const error = invite.error instanceof ApiError ? invite.error : null;

  return (
    <SidePanel
      title="Invitar persona"
      description="Le llegará un correo con un enlace para crear su cuenta o entrar con la que ya tiene."
      onClose={onClose}
      footer={
        invite.isSuccess ? (
          <div className="flex justify-end">
            <Button size="sm" onClick={onClose}>
              Listo
            </Button>
          </div>
        ) : (
          <div className="flex justify-end gap-3">
            <Button size="sm" variant="outline" onClick={onClose}>
              Cancelar
            </Button>
            <Button size="sm" type="submit" form="invite-form" disabled={!form.email.includes("@") || branchIds.length === 0} loading={invite.isPending}>
              Enviar invitación
            </Button>
          </div>
        )
      }
    >
      {invite.isSuccess ? (
        <div className="rounded-xl bg-success-50 p-5 text-theme-sm text-success-800 dark:bg-success-500/10 dark:text-success-300">
          <p className="font-semibold">Invitación enviada a {invite.data.data.email}.</p>
          <p className="mt-1">El enlace vence en 7 días. Si no le llega, revisa el correo no deseado o reenvíala desde “Invitaciones pendientes”.</p>
        </div>
      ) : (
        <form
          id="invite-form"
          className="space-y-6"
          onSubmit={(e) => {
            e.preventDefault();
            invite.mutate();
          }}
        >
          <Field label="Correo" htmlFor="inv-email" error={error?.field("email")}>
            <Input id="inv-email" type="email" autoFocus value={form.email} onChange={(e) => setForm((f) => ({ ...f, email: e.target.value }))} placeholder="nombre@correo.com" />
          </Field>
          <section>
            <h3 className="mb-2 text-theme-xs font-semibold tracking-wide text-gray-400 uppercase">Rol</h3>
            <RolePicker value={form.role} onChange={(role) => setForm((f) => ({ ...f, role }))} />
          </section>
          <section>
            <h3 className="mb-2 text-theme-xs font-semibold tracking-wide text-gray-400 uppercase">Sedes</h3>
            <BranchPicker value={branchIds} onChange={(ids) => setForm((f) => ({ ...f, branch_ids: ids }))} />
          </section>
          {error && Object.keys(error.errors).length === 0 && <p className="text-theme-sm text-error-600">{error.message}</p>}
        </form>
      )}
    </SidePanel>
  );
}

function PendingInvitations() {
  const queryClient = useQueryClient();
  const invitations = useQuery({ queryKey: ["invitations"], queryFn: () => api<{ data: Invitation[] }>("invitations").then((r) => r.data) });
  const revoke = useMutation({
    mutationFn: (id: string) => api(`invitations/${id}`, { method: "DELETE" }),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ["invitations"] }),
  });
  const resend = useMutation({
    mutationFn: (inv: Invitation) => api("invitations", { method: "POST", body: { email: inv.email, role: inv.role.name, branch_ids: inv.branch_ids ?? undefined } }),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ["invitations"] }),
  });

  if (!invitations.data || invitations.data.length === 0) {
    return invitations.data ? null : <Skeleton className="h-20" />;
  }

  return (
    <Card>
      <CardHeader title="Invitaciones pendientes" description="Aún no han aceptado." />
      {invitations.data.length === 0 ? (
        <EmptyState title="No hay invitaciones pendientes." />
      ) : (
        <ul className="divide-y divide-gray-100 dark:divide-gray-800">
          {invitations.data.map((inv) => (
            <li key={inv.id} className="flex flex-wrap items-center gap-3 px-5 py-3">
              <div className="min-w-0 flex-1">
                <p className="text-theme-sm font-medium text-gray-800 dark:text-white/90">{inv.email}</p>
                <p className="text-theme-xs text-gray-500">
                  {inv.role.label} · vence {new Date(inv.expires_at).toLocaleDateString("es-CO", { day: "numeric", month: "short" })}
                  {inv.invited_by && ` · invitó ${inv.invited_by}`}
                </p>
              </div>
              <Button size="sm" variant="ghost" loading={resend.isPending && resend.variables?.id === inv.id} onClick={() => resend.mutate(inv)}>
                Reenviar
              </Button>
              <Button size="sm" variant="ghost" loading={revoke.isPending && revoke.variables === inv.id} onClick={() => revoke.mutate(inv.id)}>
                Cancelar
              </Button>
            </li>
          ))}
        </ul>
      )}
    </Card>
  );
}
