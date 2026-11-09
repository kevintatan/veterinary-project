"use client";

import SidePanel from "@/components/common/SidePanel";
import OwnerPetPicker from "@/components/domain/OwnerPetPicker";
import { Field, Input, Select, Textarea } from "@/components/form/fields";
import { useSession } from "@/components/providers/SessionProvider";
import Button from "@/components/ui/button/Button";
import { ApiError, api } from "@/lib/api";
import type { Member } from "@/lib/settings";
import type { OwnerWithPets, Task } from "@/lib/types";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useState } from "react";

type Owner = Pick<OwnerWithPets, "id" | "full_name">;

/** Create or edit a task. Dates are local to the clinic: the API reads them in its timezone. */
export default function TaskFormPanel({ task, onClose }: { task: Task | null; onClose: () => void }) {
  const { user } = useSession();
  const queryClient = useQueryClient();
  const editable = task === null || task.can_edit;

  const [title, setTitle] = useState(task?.title ?? "");
  const [description, setDescription] = useState(task?.description ?? "");
  const [assigneeId, setAssigneeId] = useState(task?.assignee?.id ?? user.id);
  // The ISO from the API carries the clinic offset: its first 16 chars are the local date and time.
  const [dueDate, setDueDate] = useState(task?.due_at?.slice(0, 10) ?? "");
  const [dueTime, setDueTime] = useState(task?.due_at?.slice(11, 16) ?? "");
  const [owner, setOwner] = useState<Owner | null>(task?.owner ?? null);
  const [petId, setPetId] = useState(task?.pet?.id ?? "");

  const members = useQuery({
    queryKey: ["members"],
    queryFn: () => api<{ data: Member[] }>("members").then((r) => r.data),
  });

  const done = () => {
    queryClient.invalidateQueries({ queryKey: ["tasks"] });
    onClose();
  };

  const save = useMutation({
    mutationFn: () =>
      api(task ? `tasks/${task.id}` : "tasks", {
        method: task ? "PATCH" : "POST",
        body: {
          title,
          description: description || null,
          assignee_id: assigneeId,
          due_at: dueDate ? `${dueDate} ${dueTime || "23:59"}` : null,
          owner_id: owner?.id ?? null,
          pet_id: petId || null,
        },
      }),
    onSuccess: done,
  });

  const setStatus = useMutation({
    mutationFn: (status: Task["status"]["value"]) => api(`tasks/${task!.id}`, { method: "PATCH", body: { status } }),
    onSuccess: done,
  });

  const error = save.error instanceof ApiError ? save.error : null;
  const busy = save.isPending || setStatus.isPending;
  const active = (members.data ?? []).filter((m) => m.status === "active");

  return (
    <SidePanel
      title={task ? "Tarea" : "Nueva tarea"}
      description={task?.created_by ? `Creada por ${task.created_by.name}` : "Algo que alguien del equipo debe hacer."}
      onClose={onClose}
      footer={
        <div className="flex flex-wrap items-center justify-between gap-2">
          <div className="flex gap-2">
            {task && editable && task.status.value === "pending" && (
              <>
                <Button size="sm" variant="outline" disabled={busy} onClick={() => setStatus.mutate("done")}>
                  Marcar como hecha
                </Button>
                <Button size="sm" variant="ghost" disabled={busy} onClick={() => setStatus.mutate("discarded")}>
                  Descartar
                </Button>
              </>
            )}
            {task && editable && task.status.value !== "pending" && (
              <Button size="sm" variant="outline" disabled={busy} onClick={() => setStatus.mutate("pending")}>
                Reabrir
              </Button>
            )}
          </div>
          {editable && (
            <Button size="sm" type="submit" form="task-form" loading={save.isPending} disabled={!title.trim() || busy}>
              {task ? "Guardar" : "Crear tarea"}
            </Button>
          )}
        </div>
      }
    >
      <form
        id="task-form"
        className="space-y-5"
        onSubmit={(e) => {
          e.preventDefault();
          if (title.trim()) save.mutate();
        }}
      >
        {!editable && (
          <p className="rounded-lg bg-gray-50 px-4 py-3 text-theme-sm text-gray-600 dark:bg-white/5 dark:text-gray-300">
            Solo quien la creó, su responsable o un administrador puede modificarla.
          </p>
        )}
        {error && !Object.keys(error.errors).length && <p className="text-theme-sm text-error-600">{error.message}</p>}

        <Field label="Qué hay que hacer" htmlFor="task-title" error={error?.field("title")}>
          <Input id="task-title" value={title} onChange={(e) => setTitle(e.target.value)} maxLength={200} disabled={!editable} autoFocus={!task} placeholder="Ej.: Llamar a Luz para confirmar resultado de laboratorio" />
        </Field>

        <Field label="Detalle" htmlFor="task-description" optional>
          <Textarea id="task-description" rows={3} value={description} onChange={(e) => setDescription(e.target.value)} disabled={!editable} />
        </Field>

        <Field label="Responsable" htmlFor="task-assignee" error={error?.field("assignee_id")}>
          <Select id="task-assignee" value={assigneeId} onChange={(e) => setAssigneeId(e.target.value)} disabled={!editable}>
            {active.length === 0 && <option value={assigneeId}>{task?.assignee?.name ?? user.name}</option>}
            {active.map((m) => (
              <option key={m.user.id} value={m.user.id}>
                {m.user.name}
                {m.user.id === user.id ? " (yo)" : ""}
              </option>
            ))}
          </Select>
        </Field>

        {/* Own row: date + time don't fit next to the assignee in the side panel. */}
        <div className="grid grid-cols-2 gap-4">
          <Field label="Fecha límite" htmlFor="task-due" optional error={error?.field("due_at")}>
            <Input id="task-due" type="date" value={dueDate} onChange={(e) => setDueDate(e.target.value)} disabled={!editable} />
          </Field>
          <Field label="Hora" htmlFor="task-time" optional hint={dueDate && !dueTime ? "Sin hora: vence al final del día." : undefined}>
            <Input id="task-time" type="time" value={dueTime} onChange={(e) => setDueTime(e.target.value)} disabled={!editable || !dueDate} />
          </Field>
        </div>

        {editable ? (
          <OwnerPetPicker
            id="task-owner"
            hint="opcional"
            owner={owner}
            petId={petId}
            onChange={(o, pet) => {
              setOwner(o);
              setPetId(pet);
            }}
            error={error?.field("pet_id") ?? error?.field("owner_id")}
          />
        ) : (
          (task?.owner || task?.pet) && (
            <p className="text-theme-sm text-gray-600 dark:text-gray-300">
              {[task?.owner?.full_name, task?.pet?.name].filter(Boolean).join(" · ")}
            </p>
          )
        )}
      </form>
    </SidePanel>
  );
}
