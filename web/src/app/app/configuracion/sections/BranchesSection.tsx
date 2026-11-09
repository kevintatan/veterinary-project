"use client";

import { Card, CardHeader, Skeleton } from "@/components/common/Card";
import SidePanel from "@/components/common/SidePanel";
import { Field, Input } from "@/components/form/fields";
import Button from "@/components/ui/button/Button";
import { api, ApiError } from "@/lib/api";
import type { Branch } from "@/lib/settings";
import { cn } from "@/utils";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useRouter } from "next/navigation";
import { useState } from "react";
import { Switch } from "./ClinicSection";

export default function BranchesSection() {
  const [editing, setEditing] = useState<Branch | "new" | null>(null);
  const branches = useQuery({ queryKey: ["branches"], queryFn: () => api<{ data: Branch[] }>("branches").then((r) => r.data) });

  return (
    <Card>
      <CardHeader
        title="Sedes"
        description="Cada sede tiene su agenda, sus horarios y su equipo."
        action={
          <Button size="sm" onClick={() => setEditing("new")}>
            Nueva sede
          </Button>
        }
      />
      {!branches.data ? (
        <Skeleton className="m-5 h-24" />
      ) : (
        <ul className="divide-y divide-gray-100 dark:divide-gray-800">
          {branches.data.map((b) => (
            <li key={b.id} className={cn("flex items-center gap-4 px-5 py-4", !b.is_active && "opacity-60")}>
              <div className="min-w-0 flex-1">
                <p className="font-medium text-gray-800 dark:text-white/90">
                  {b.name}
                  {!b.is_active && <span className="ms-2 rounded-full bg-gray-100 px-2 py-0.5 text-theme-xs text-gray-500 dark:bg-white/5">Inactiva</span>}
                </p>
                <p className="truncate text-theme-sm text-gray-500 dark:text-gray-400">{[b.address, b.phone].filter(Boolean).join(" · ") || "Sin dirección ni teléfono"}</p>
              </div>
              <Button size="sm" variant="outline" onClick={() => setEditing(b)}>
                Editar
              </Button>
            </li>
          ))}
        </ul>
      )}
      {editing && <BranchPanel branch={editing === "new" ? undefined : editing} onClose={() => setEditing(null)} />}
    </Card>
  );
}

function BranchPanel({ branch, onClose }: { branch?: Branch; onClose: () => void }) {
  const queryClient = useQueryClient();
  const router = useRouter();
  const [form, setForm] = useState({ name: branch?.name ?? "", address: branch?.address ?? "", phone: branch?.phone ?? "", is_active: branch?.is_active ?? true });

  const save = useMutation({
    mutationFn: () => {
      const body = { name: form.name.trim(), address: form.address.trim() || null, phone: form.phone.trim() || null, is_active: form.is_active };
      return branch ? api(`branches/${branch.id}`, { method: "PATCH", body }) : api("branches", { method: "POST", body });
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["branches"] });
      router.refresh(); // the agenda's branch selector comes from the session
      onClose();
    },
  });
  const error = save.error instanceof ApiError ? save.error : null;

  return (
    <SidePanel
      title={branch ? `Editar ${branch.name}` : "Nueva sede"}
      onClose={onClose}
      footer={
        <div className="flex justify-end gap-3">
          <Button size="sm" variant="outline" onClick={onClose}>
            Cancelar
          </Button>
          <Button size="sm" type="submit" form="branch-form" disabled={form.name.trim().length < 2} loading={save.isPending}>
            Guardar
          </Button>
        </div>
      }
    >
      <form
        id="branch-form"
        className="space-y-4"
        onSubmit={(e) => {
          e.preventDefault();
          save.mutate();
        }}
      >
        <Field label="Nombre" htmlFor="br-name" error={error?.field("name")}>
          <Input id="br-name" autoFocus value={form.name} onChange={(e) => setForm((f) => ({ ...f, name: e.target.value }))} placeholder="Sede Cabecera" maxLength={120} />
        </Field>
        <Field label="Dirección" htmlFor="br-address" optional>
          <Input id="br-address" value={form.address} onChange={(e) => setForm((f) => ({ ...f, address: e.target.value }))} placeholder="Cra 33 # 48-20, Bucaramanga" />
        </Field>
        <Field label="Teléfono" htmlFor="br-phone" optional>
          <Input id="br-phone" value={form.phone} onChange={(e) => setForm((f) => ({ ...f, phone: e.target.value }))} maxLength={20} />
        </Field>
        {branch && (
          <div className="flex items-center justify-between rounded-lg border border-gray-200 px-4 py-3 dark:border-gray-800">
            <div>
              <p className="text-theme-sm font-medium text-gray-800 dark:text-white/90">Sede activa</p>
              <p className="text-theme-xs text-gray-500">Inactiva: no aparece al agendar. Su historial se conserva.</p>
            </div>
            <Switch checked={form.is_active} label="Sede activa" onChange={(v) => setForm((f) => ({ ...f, is_active: v }))} />
          </div>
        )}
        {error && Object.keys(error.errors).length === 0 && <p className="text-theme-sm text-error-600">{error.message}</p>}
      </form>
    </SidePanel>
  );
}
