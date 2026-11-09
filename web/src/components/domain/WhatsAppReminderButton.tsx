"use client";

import { api, ApiError } from "@/lib/api";
import { cn } from "@/utils";
import { useMutation, useQueryClient } from "@tanstack/react-query";

/**
 * Manual reminder (Fase 4a): the API writes the message (same wording the
 * automatic reminders will use), checks the owner's WhatsApp authorization,
 * logs it and returns a wa.me link that opens the clinic's WhatsApp.
 */
export default function WhatsAppReminderButton({
  type,
  id,
  allowed,
  blockedReason,
  size = "sm",
  label = "Recordar",
  className,
}: {
  type: "appointment" | "control";
  id: string;
  allowed: boolean;
  blockedReason?: string | null;
  size?: "sm" | "md";
  label?: string;
  className?: string;
}) {
  const queryClient = useQueryClient();

  const send = useMutation({
    mutationFn: async () => {
      // Opened inside the click so the browser doesn't block it as a popup; the link is set when it arrives.
      const tab = window.open("", "_blank");
      try {
        const { data } = await api<{ data: { url: string } }>("reminders/manual", { method: "POST", body: { type, id } });
        if (tab) tab.location.href = data.url;
        else window.location.assign(data.url);
      } catch (error) {
        tab?.close();
        throw error;
      }
    },
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ["reminders"] }),
  });

  const error = send.error instanceof ApiError ? (send.error.field("id") ?? send.error.message) : null;

  return (
    <span className={cn("inline-flex flex-col items-end", className)}>
      <button
        type="button"
        disabled={!allowed || send.isPending}
        onClick={() => send.mutate()}
        title={allowed ? "Abre WhatsApp con el mensaje listo para enviar" : (blockedReason ?? undefined)}
        className={cn(
          "inline-flex items-center gap-1.5 rounded-lg font-medium transition disabled:cursor-not-allowed",
          size === "sm" ? "px-2.5 py-1.5 text-theme-xs" : "px-3.5 py-2 text-theme-sm",
          allowed
            ? "bg-[#1f9d55] text-white hover:bg-[#178a49] disabled:opacity-60"
            : "bg-gray-100 text-gray-400 dark:bg-white/5 dark:text-gray-500",
        )}
      >
        <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor" aria-hidden>
          <path d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.3A10 10 0 1 0 12 2Zm4.5 12.1c-.2-.1-1.5-.7-1.7-.8-.2-.1-.4-.1-.6.1l-.8 1c-.1.2-.3.2-.5.1a6.7 6.7 0 0 1-3.3-2.9c-.2-.4.2-.4.7-1.3.1-.2 0-.3 0-.4l-.8-1.8c-.2-.5-.4-.4-.6-.4h-.5a1 1 0 0 0-.7.3 3 3 0 0 0-.9 2.2 5.2 5.2 0 0 0 1.1 2.7 11.8 11.8 0 0 0 4.5 4c1.7.7 2.3.8 3.2.6.5-.1 1.5-.6 1.7-1.2.2-.6.2-1.1.2-1.2-.1-.1-.3-.2-.5-.3Z" />
        </svg>
        {send.isPending ? "Abriendo…" : label}
      </button>
      {error && <span className="mt-1 max-w-56 text-right text-[11px] text-error-500">{error}</span>}
    </span>
  );
}
