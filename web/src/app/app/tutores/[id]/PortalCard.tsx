"use client";

import { Card, CardHeader, Skeleton } from "@/components/common/Card";
import { useCan } from "@/components/providers/SessionProvider";
import Button from "@/components/ui/button/Button";
import { ApiError, api } from "@/lib/api";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useState } from "react";

type PortalStatus = {
  active: boolean;
  linked_at: string | null;
  last_login_at: string | null;
  pending_invitation: { created_at: string; expires_at: string } | null;
  can_invite: boolean;
  has_email: boolean;
};

type Channel = "whatsapp" | "email" | "link";

const day = (iso: string) => new Date(iso).toLocaleDateString("es-CO", { day: "numeric", month: "short" }).replace(/\./g, "");

/**
 * Owner portal (Fase 4e): the clinic invites the owner once; they activate it
 * and keep the session. Sending is free: the clinic's own WhatsApp (text
 * ready, no Meta template), email, or copying the link.
 */
export default function PortalCard({ ownerId }: { ownerId: string }) {
  const queryClient = useQueryClient();
  const canInvite = useCan("patients.manage");
  const [copied, setCopied] = useState(false);
  const key = ["owners", ownerId, "portal"];
  const status = useQuery({ queryKey: key, queryFn: () => api<{ data: PortalStatus }>(`owners/${ownerId}/portal`).then((r) => r.data) });

  const invite = useMutation({
    mutationFn: (channel: Channel) =>
      api<{ data: { url: string; text: string; whatsapp_url: string | null; status: PortalStatus } }>(`owners/${ownerId}/portal-invitation`, {
        method: "POST",
        body: { channel },
      }).then((r) => ({ channel, ...r.data })),
    onSuccess: async ({ channel, url, whatsapp_url, status: next }) => {
      queryClient.setQueryData(key, next);
      if (channel === "whatsapp" && whatsapp_url) window.open(whatsapp_url, "_blank", "noopener");
      if (channel === "link") {
        await navigator.clipboard.writeText(url).catch(() => undefined);
        setCopied(true);
        setTimeout(() => setCopied(false), 2500);
      }
    },
  });

  const s = status.data;
  const error = invite.error instanceof ApiError ? (Object.values(invite.error.errors)[0]?.[0] ?? invite.error.message) : null;

  return (
    <Card className="h-fit">
      <CardHeader title="Portal del tutor" description="Carné con QR, vacunas y citas en su celular." />
      {!s ? (
        <Skeleton className="m-5 h-12" />
      ) : (
        <div className="space-y-3 px-5 py-4">
          <p className="text-theme-sm">
            {s.active ? (
              <span className="text-success-700 dark:text-success-400">
                ● Activo{s.last_login_at ? ` · último ingreso ${day(s.last_login_at)}` : ""}
              </span>
            ) : s.pending_invitation ? (
              <span className="text-warning-700 dark:text-warning-400">● Invitado el {day(s.pending_invitation.created_at)} · vence el {day(s.pending_invitation.expires_at)}</span>
            ) : (
              <span className="text-gray-500">Aún no lo ha activado.</span>
            )}
          </p>

          {!s.can_invite ? (
            <p className="text-theme-xs text-gray-500">Registra su celular para poder invitarlo: es su identidad en el portal.</p>
          ) : (
            canInvite &&
            !s.active && (
              <div className="flex flex-wrap gap-2">
                <Button size="sm" loading={invite.isPending && invite.variables === "whatsapp"} onClick={() => invite.mutate("whatsapp")}>
                  {s.pending_invitation ? "Reenviar por WhatsApp" : "Invitar por WhatsApp"}
                </Button>
                {s.has_email && (
                  <Button size="sm" variant="outline" loading={invite.isPending && invite.variables === "email"} onClick={() => invite.mutate("email")}>
                    Por correo
                  </Button>
                )}
                <Button size="sm" variant="ghost" loading={invite.isPending && invite.variables === "link"} onClick={() => invite.mutate("link")}>
                  {copied ? "¡Enlace copiado!" : "Copiar enlace"}
                </Button>
              </div>
            )
          )}
          {invite.isSuccess && invite.data.channel === "email" && <p className="text-theme-xs text-success-700">Invitación enviada a su correo.</p>}
          {error && <p className="text-theme-xs text-error-600">{error}</p>}
          {canInvite && !s.active && s.can_invite && (
            <p className="text-theme-xs text-gray-400">Cada invitación nueva reemplaza la anterior. El enlace sirve una sola vez y vence en 7 días.</p>
          )}
        </div>
      )}
    </Card>
  );
}
