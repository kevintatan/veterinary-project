"use client";

import { Card, CardHeader, EmptyState, Skeleton } from "@/components/common/Card";
import WhatsAppText from "@/components/domain/WhatsAppText";
import { api } from "@/lib/api";
import { MESSAGE_PURPOSE, REMINDER_BUTTON_LABELS, simulateOwner } from "@/lib/conversations";
import { cn } from "@/utils";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import Link from "next/link";
import { useState } from "react";

type OwnerMessage = {
  id: string;
  direction: "outbound" | "inbound";
  channel: string;
  provider: "manual" | "fake" | "meta";
  purpose: string | null;
  body: string | null;
  buttons: string[];
  status: string;
  sent_by: string | null;
  sent_at: string;
  delivered_at: string | null;
  read_at: string | null;
};

const PURPOSE = MESSAGE_PURPOSE;

function statusText(m: OwnerMessage): string {
  if (m.direction === "inbound") return "Recibido";
  const simulated = m.provider === "fake" ? " (simulado: no salió)" : "";
  if (m.purpose === "staff_reply") return `Respondido por ${m.sent_by ?? "la clínica"}${simulated}`;
  if (m.purpose === "bot_reply") return `Respuesta del bot${simulated}`;
  if (m.provider === "manual") return `Abierto en WhatsApp${m.sent_by ? ` por ${m.sent_by}` : ""}`;
  if (m.provider === "fake") return "Automático (simulado: no salió)";
  if (m.read_at) return "Leído";
  if (m.delivered_at) return "Entregado";
  return m.status === "failed" ? "Falló" : "Enviado";
}

/** Every message to this owner (manual or automatic), newest first. */
export default function MessagesCard({ ownerId, phone }: { ownerId: string; phone: string | null }) {
  const [open, setOpen] = useState<string | null>(null);
  const queryClient = useQueryClient();
  const query = useQuery({
    queryKey: ["owners", ownerId, "messages"],
    queryFn: () => api<{ data: OwnerMessage[]; meta: { simulated: boolean } }>(`owners/${ownerId}/messages`),
  });
  const messages = { data: query.data?.data };
  // Development (WhatsApp not connected yet): tap the reminder buttons as if you were the owner.
  const simulate = useMutation({
    mutationFn: (payload: string) => simulateOwner(phone!, { payload, title: REMINDER_BUTTON_LABELS[payload.split(":")[0]] }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["owners", ownerId, "messages"] });
      queryClient.invalidateQueries({ queryKey: ["conversations"] });
      queryClient.invalidateQueries({ queryKey: ["appointments"] });
    },
  });

  return (
    <Card className="h-fit">
      <CardHeader title="Mensajes" description="Recordatorios y conversación por WhatsApp con este tutor." />
      {!messages.data ? (
        <Skeleton className="m-5 h-16" />
      ) : messages.data.length === 0 ? (
        <EmptyState title="Aún no se le ha escrito." />
      ) : (
        <ul className="divide-y divide-gray-100 dark:divide-gray-800">
          {messages.data.slice(0, 10).map((m) => (
            <li key={m.id}>
              <button type="button" onClick={() => setOpen((o) => (o === m.id ? null : m.id))} className="w-full px-5 py-3 text-left hover:bg-gray-50 dark:hover:bg-white/2">
                <div className="flex items-center justify-between gap-3">
                  <span className="text-theme-sm font-medium text-gray-800 dark:text-white/90">{PURPOSE[m.purpose ?? ""] ?? "Mensaje"}</span>
                  <span className="shrink-0 text-theme-xs text-gray-500 tabular">
                    {new Date(m.sent_at).toLocaleString("es-CO", { day: "numeric", month: "short", hour: "numeric", minute: "2-digit" }).replace(/\./g, "")}
                  </span>
                </div>
                <p className={cn("text-theme-xs", m.provider === "fake" ? "text-warning-700 dark:text-warning-400" : "text-gray-500")}>{statusText(m)}</p>
              </button>
              {open === m.id && m.body && (
                <div className="px-5 pb-3">
                  <p className="rounded-2xl rounded-tl-sm bg-[#e7f8ee] px-3.5 py-2.5 text-theme-sm whitespace-pre-line text-gray-800 dark:bg-success-500/10 dark:text-gray-200"><WhatsAppText text={m.body} /></p>
                  {m.buttons.length > 0 && query.data?.meta.simulated && phone && (
                    <div className="mt-2">
                      <p className="mb-1 text-theme-xs text-warning-700 dark:text-warning-400">Simular respuesta del tutor:</p>
                      <div className="flex flex-wrap gap-1.5">
                        {m.buttons.map((b) => (
                          <button
                            key={b}
                            type="button"
                            disabled={simulate.isPending}
                            onClick={() => simulate.mutate(b)}
                            className="rounded-lg border border-gray-200 bg-white px-3 py-1 text-theme-xs font-medium text-[#027eb5] hover:bg-gray-50 disabled:opacity-50 dark:border-gray-700 dark:bg-gray-800 dark:text-sky-400"
                          >
                            {REMINDER_BUTTON_LABELS[b.split(":")[0]] ?? b}
                          </button>
                        ))}
                      </div>
                      {simulate.isSuccess && (
                        <Link href="/app/whatsapp" className="mt-1.5 inline-block text-theme-xs font-medium text-brand-600 hover:underline">
                          Ver la conversación en WhatsApp →
                        </Link>
                      )}
                    </div>
                  )}
                </div>
              )}
            </li>
          ))}
        </ul>
      )}
    </Card>
  );
}
