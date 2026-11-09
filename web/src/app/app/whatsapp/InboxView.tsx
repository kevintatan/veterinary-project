"use client";

import { Card, EmptyState, Skeleton } from "@/components/common/Card";
import PageHeader from "@/components/common/PageHeader";
import WhatsAppText from "@/components/domain/WhatsAppText";
import { Textarea } from "@/components/form/fields";
import { useCan } from "@/components/providers/SessionProvider";
import Button from "@/components/ui/button/Button";
import { ApiError, api } from "@/lib/api";
import {
  type Conversation,
  type ThreadMessage,
  fetchInbox,
  inboxKey,
  MESSAGE_PURPOSE,
  REMINDER_BUTTON_LABELS,
  simulateOwner,
} from "@/lib/conversations";
import { cn } from "@/utils";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import Link from "next/link";
import { useEffect, useRef, useState } from "react";

const time = (iso: string) =>
  new Date(iso)
    .toLocaleString("es-CO", {
      day: "numeric",
      month: "short",
      hour: "numeric",
      minute: "2-digit",
    })
    .replace(/\./g, "");

/**
 * WhatsApp inbox (Fase 4d). The bot answers the buttons by itself; what it
 * can't handle (free text) lands here for the team to answer.
 */
export default function InboxView() {
  const [filter, setFilter] = useState<"attention" | "all">("attention");
  const [selected, setSelected] = useState<string | null>(null);
  const inbox = useQuery({
    queryKey: inboxKey(filter),
    queryFn: () => fetchInbox(filter),
    refetchInterval: 15_000,
  });

  return (
    <>
      <PageHeader
        title="WhatsApp"
        subtitle="Los tutores responden a los recordatorios con botones y el bot confirma, reprograma o cancela solo. Lo que el bot no entiende llega aquí."
      />

      {inbox.data?.meta.enabled === false ? (
        <Card>
          <EmptyState
            title="Tu plan no incluye la bandeja de WhatsApp"
            description="El bot igual confirma, reprograma y cancela citas con los botones. Si el tutor escribe otra cosa, se le indica el WhatsApp de la clínica."
          />
        </Card>
      ) : (
        <>
          {inbox.data?.meta.simulated && (
            <p className="mb-4 rounded-xl border border-warning-200 bg-warning-50 px-4 py-3 text-theme-sm text-warning-800 dark:border-warning-500/30 dark:bg-warning-500/10 dark:text-warning-300">
              <b>Modo simulado:</b> WhatsApp aún no está conectado, nada sale
              del servidor. Puedes hacer de tutor: toca los botones de los
              mensajes o escribe como él.
            </p>
          )}

          <div className="grid gap-4 lg:grid-cols-[320px_1fr]">
            <Card className="h-fit overflow-hidden">
              <div className="flex gap-1 border-b border-gray-100 p-2 dark:border-gray-800">
                {(
                  [
                    [
                      "attention",
                      `Por responder${inbox.data ? ` (${inbox.data.meta.attention})` : ""}`,
                    ],
                    ["all", "Todas"],
                  ] as const
                ).map(([value, label]) => (
                  <button
                    key={value}
                    type="button"
                    onClick={() => setFilter(value)}
                    className={cn(
                      "flex-1 rounded-lg px-3 py-2 text-theme-sm font-medium",
                      filter === value
                        ? "bg-gray-100 text-gray-900 dark:bg-white/5 dark:text-white"
                        : "text-gray-500 hover:text-gray-700 dark:hover:text-gray-300",
                    )}
                  >
                    {label}
                  </button>
                ))}
              </div>

              {!inbox.data ? (
                <Skeleton className="m-4 h-24" />
              ) : inbox.data.data.length === 0 ? (
                <EmptyState
                  title={
                    filter === "attention"
                      ? "Nada pendiente"
                      : "Aún no hay conversaciones"
                  }
                  description={
                    filter === "attention"
                      ? "El bot está atendiendo todo. 🎉"
                      : "Aparecen cuando un tutor responde un recordatorio."
                  }
                />
              ) : (
                <ul className="divide-y divide-gray-100 dark:divide-gray-800">
                  {inbox.data.data.map((c) => (
                    <li key={c.id}>
                      <button
                        type="button"
                        onClick={() => setSelected(c.id)}
                        className={cn(
                          "w-full px-4 py-3 text-left hover:bg-gray-50 dark:hover:bg-white/2",
                          selected === c.id &&
                            "bg-brand-25 dark:bg-brand-500/10",
                        )}
                      >
                        <div className="flex items-center justify-between gap-2">
                          <span className="truncate text-theme-sm font-medium text-gray-800 dark:text-white/90">
                            {c.owner?.full_name ?? c.phone}
                          </span>
                          {c.needs_attention_since && (
                            <span
                              className="size-2 shrink-0 rounded-full bg-success-500"
                              aria-label="Por responder"
                            />
                          )}
                        </div>
                        {c.last_message && (
                          <p className="truncate text-theme-xs text-gray-500">
                            {c.last_message.direction === "outbound" && "Tú: "}
                            {c.last_message.body?.replace(/\*/g, "")}
                          </p>
                        )}
                      </button>
                    </li>
                  ))}
                </ul>
              )}
            </Card>

            {selected ? (
              <Thread
                id={selected}
                simulated={inbox.data?.meta.simulated ?? false}
              />
            ) : (
              <Card>
                <EmptyState title="Elige una conversación" />
              </Card>
            )}
          </div>
        </>
      )}
    </>
  );
}

function Thread({ id, simulated }: { id: string; simulated: boolean }) {
  const queryClient = useQueryClient();
  const canReply = useCan("appointments.manage");
  const [text, setText] = useState("");
  const [asOwner, setAsOwner] = useState("");
  const scroller = useRef<HTMLDivElement>(null);

  const thread = useQuery({
    queryKey: ["conversations", "thread", id],
    queryFn: () =>
      api<{ data: Conversation }>(`conversations/${id}`).then((r) => r.data),
    refetchInterval: 10_000,
  });

  const refresh = () =>
    queryClient.invalidateQueries({ queryKey: ["conversations"] });

  const reply = useMutation({
    mutationFn: () =>
      api(`conversations/${id}/reply`, { method: "POST", body: { text } }),
    onSuccess: () => {
      setText("");
      refresh();
    },
  });
  const resolve = useMutation({
    mutationFn: () => api(`conversations/${id}/resolve`, { method: "POST" }),
    onSuccess: refresh,
  });
  const simulate = useMutation({
    mutationFn: (input: Parameters<typeof simulateOwner>[1]) =>
      simulateOwner(thread.data!.phone, input),
    onSuccess: () => {
      setAsOwner("");
      refresh();
      // Appointment status may have changed (confirmed, moved, cancelled).
      queryClient.invalidateQueries({ queryKey: ["appointments"] });
    },
  });

  const count = thread.data?.messages.length;
  // Scroll only the chat (scrollIntoView would also move the page).
  useEffect(() => {
    if (scroller.current)
      scroller.current.scrollTop = scroller.current.scrollHeight;
  }, [count, id]);

  if (!thread.data) {
    return (
      <Card>
        <Skeleton className="m-5 h-64" />
      </Card>
    );
  }

  const c = thread.data;
  const error =
    reply.error instanceof ApiError
      ? (reply.error.field("text") ?? reply.error.message)
      : null;

  return (
    <Card className="flex min-h-[520px] flex-col">
      <header className="flex items-center justify-between gap-3 border-b border-gray-100 px-5 py-3 dark:border-gray-800">
        <div>
          {c.owner ? (
            <Link
              href={`/app/tutores/${c.owner.id}`}
              className="font-semibold text-gray-800 hover:text-brand-600 dark:text-white/90"
            >
              {c.owner.full_name}
            </Link>
          ) : (
            <span className="font-semibold text-gray-800 dark:text-white/90">
              Número sin tutor registrado
            </span>
          )}
          <p className="text-theme-xs text-gray-500 tabular">{c.phone}</p>
        </div>
        {c.needs_attention_since && canReply && (
          <Button
            size="sm"
            variant="outline"
            loading={resolve.isPending}
            onClick={() => resolve.mutate()}
          >
            Marcar como resuelta
          </Button>
        )}
      </header>

      <div
        ref={scroller}
        className="flex-1 space-y-3 overflow-y-auto bg-[#efeae2] px-4 py-4 dark:bg-gray-950"
        style={{ maxHeight: 520 }}
      >
        {c.messages.map((m) => (
          <Bubble
            key={m.id}
            m={m}
            simulated={simulated && !simulate.isPending}
            onTap={(payload, title) => simulate.mutate({ payload, title })}
          />
        ))}
      </div>

      {simulated && (
        <form
          className="flex gap-2 border-t border-dashed border-warning-300 bg-warning-25 px-4 py-2 dark:border-warning-500/30 dark:bg-warning-500/5"
          onSubmit={(e) => {
            e.preventDefault();
            if (asOwner.trim()) simulate.mutate({ text: asOwner.trim() });
          }}
        >
          <input
            value={asOwner}
            onChange={(e) => setAsOwner(e.target.value)}
            placeholder="Escribir como el tutor (simulado)…"
            className="flex-1 rounded-lg border border-warning-200 bg-white px-3 py-1.5 text-theme-sm dark:border-warning-500/30 dark:bg-gray-900"
          />
          <Button
            size="sm"
            variant="outline"
            type="submit"
            loading={simulate.isPending}
          >
            Enviar como tutor
          </Button>
        </form>
      )}

      {canReply && (
        <form
          className="border-t border-gray-100 p-4 dark:border-gray-800"
          onSubmit={(e) => {
            e.preventDefault();
            if (text.trim()) reply.mutate();
          }}
        >
          {c.window_open ? (
            <div className="flex items-end gap-2">
              <Textarea
                rows={2}
                value={text}
                onChange={(e) => setText(e.target.value)}
                placeholder="Escribe la respuesta de la clínica…"
                className="flex-1"
              />
              <Button
                type="submit"
                loading={reply.isPending}
                disabled={!text.trim()}
              >
                Responder
              </Button>
            </div>
          ) : (
            <p className="text-theme-sm text-gray-500">
              Pasaron más de 24 horas desde el último mensaje del tutor:
              WhatsApp solo permite escribirle con plantillas aprobadas. Llámalo
              o espera a que vuelva a escribir.
            </p>
          )}
          {c.window_open && c.window_closes_at && (
            <p className="mt-1 text-theme-xs text-gray-400">
              Puedes responder hasta el {time(c.window_closes_at)} (ventana de
              24 h de WhatsApp).
            </p>
          )}
          {error && (
            <p className="mt-1 text-theme-xs text-error-600">{error}</p>
          )}
        </form>
      )}
    </Card>
  );
}

function Bubble({
  m,
  simulated,
  onTap,
}: {
  m: ThreadMessage;
  simulated: boolean;
  onTap: (payload: string, title: string) => void;
}) {
  const mine = m.direction === "outbound";
  const label = MESSAGE_PURPOSE[m.purpose ?? ""];

  return (
    <div className={cn("flex", mine ? "justify-end" : "justify-start")}>
      <div className="max-w-[80%]">
        <div
          className={cn(
            "rounded-lg px-3 py-2 text-theme-sm shadow-xs",
            mine
              ? "rounded-tr-none bg-[#d9fdd3] text-gray-800 dark:bg-success-500/20 dark:text-white/90"
              : "rounded-tl-none bg-white text-gray-800 dark:bg-gray-800 dark:text-white/90",
          )}
        >
          {label && (
            <p className="mb-0.5 text-[11px] font-medium text-gray-500">
              {label}
              {m.sent_by ? ` · ${m.sent_by}` : ""}
            </p>
          )}
          <p className="whitespace-pre-line">
            <WhatsAppText text={m.body ?? ""} />
          </p>
          <p className="mt-1 text-right text-[10px] text-gray-500 tabular">
            {time(m.at)}
          </p>
        </div>

        {m.buttons.length > 0 && (
          <div className="mt-1 grid gap-1">
            {m.buttons.map((b) => {
              const title =
                b.title ?? REMINDER_BUTTON_LABELS[b.id.split(":")[0]] ?? b.id;
              return (
                <button
                  key={b.id}
                  type="button"
                  disabled={!simulated}
                  onClick={() => onTap(b.id, title)}
                  title={
                    simulated
                      ? "Simular que el tutor toca este botón"
                      : undefined
                  }
                  className={cn(
                    "rounded-lg bg-white px-3 py-1.5 text-center text-theme-sm font-medium text-[#027eb5] shadow-xs dark:bg-gray-800 dark:text-sky-400",
                    simulated
                      ? "hover:bg-gray-50 dark:hover:bg-gray-700"
                      : "cursor-default opacity-90",
                  )}
                >
                  {title}
                </button>
              );
            })}
          </div>
        )}
      </div>
    </div>
  );
}
