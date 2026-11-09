import { api } from "./api";

/** WhatsApp inbox (Fase 4d): conversations the bot handed to the clinic. */
export type ConversationSummary = {
  id: string;
  phone: string;
  owner: { id: string; full_name: string } | null;
  needs_attention_since: string | null;
  window_open: boolean;
  last_message: { body: string | null; direction: "inbound" | "outbound"; at: string } | null;
};

export type ThreadMessage = {
  id: string;
  direction: "inbound" | "outbound";
  provider: "manual" | "fake" | "meta";
  purpose: string | null;
  body: string | null;
  buttons: { id: string; title: string | null }[];
  status: string;
  sent_by: string | null;
  at: string;
};

export type Conversation = Omit<ConversationSummary, "last_message"> & {
  window_closes_at: string | null;
  messages: ThreadMessage[];
};

/** enabled: the plan includes the chat with the team (features.whatsapp_inbox, docs/planes.md). */
export type Inbox = { data: ConversationSummary[]; meta: { enabled: boolean; attention: number; simulated: boolean } };

export const inboxKey = (filter: string) => ["conversations", filter] as const;

export const fetchInbox = (filter: "attention" | "all") => api<Inbox>(`conversations?filter=${filter}`);

/** Labels of the reminder template buttons (the payloads only carry the action). */
export const REMINDER_BUTTON_LABELS: Record<string, string> = {
  confirm: "Confirmar",
  reschedule: "Reprogramar",
  cancel: "Cancelar",
};

/** Development only (WHATSAPP_DRIVER=fake): act as the owner. */
export function simulateOwner(phone: string, input: { payload: string; title?: string } | { text: string }) {
  return api("dev/whatsapp/simulate", { method: "POST", body: { phone, ...input } });
}

export const MESSAGE_PURPOSE: Record<string, string> = {
  appointment_reminder: "Recordatorio de cita",
  next_control: "Recordatorio de vacuna o control",
  bot_reply: "Respuesta automática",
  staff_reply: "Respuesta de la clínica",
  button_reply: "Respuesta con botón",
  owner_message: "Mensaje del tutor",
};
