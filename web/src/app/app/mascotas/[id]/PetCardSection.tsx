"use client";

import { Card, CardHeader } from "@/components/common/Card";
import { Input } from "@/components/form/fields";
import Button from "@/components/ui/button/Button";
import { api, ApiError } from "@/lib/api";
import { fromE164, type Pet, toE164 } from "@/lib/patients";
import { cn } from "@/utils";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import Link from "next/link";
import { useState } from "react";

type CardState = {
  public_url: string;
  public_profile_enabled: boolean;
  lost_mode_since: string | null;
  public_contact_phone: string | null;
  total: number;
  views: { viewed_at: string; in_lost_mode: boolean; device: string }[];
};

/**
 * Digital card (Fase 4b): QR, printable card, public page switch, lost mode
 * and the latest scans.
 */
export default function PetCardSection({ pet, canManage }: { pet: Pet; canManage: boolean }) {
  const queryClient = useQueryClient();
  const [confirmRegen, setConfirmRegen] = useState(false);
  const [phone, setPhone] = useState("");
  const [qrVersion, setQrVersion] = useState(0);

  const state = useQuery({
    queryKey: ["pets", pet.id, "card"],
    queryFn: () => api<{ data: CardState }>(`pets/${pet.id}/public-views`).then((r) => r.data),
    refetchInterval: (q) => (q.state.data?.lost_mode_since ? 30_000 : false), // watch scans while lost
  });
  const s = state.data;
  const refresh = () => {
    queryClient.invalidateQueries({ queryKey: ["pets", pet.id] });
  };

  const lost = useMutation({
    mutationFn: (enabled: boolean) => api(`pets/${pet.id}/lost-mode`, { method: "POST", body: { enabled, contact_phone: enabled ? toE164(phone) : null } }),
    onSuccess: refresh,
  });
  const visibility = useMutation({
    mutationFn: (enabled: boolean) => api(`pets/${pet.id}`, { method: "PATCH", body: { public_profile_enabled: enabled } }),
    onSuccess: refresh,
  });
  const regenerate = useMutation({
    mutationFn: () => api(`pets/${pet.id}/public-token`, { method: "POST" }),
    onSuccess: () => {
      setConfirmRegen(false);
      setQrVersion((v) => v + 1);
      refresh();
    },
  });

  const lostError = lost.error instanceof ApiError ? (lost.error.field("contact_phone") ?? lost.error.message) : null;
  const isLost = !!s?.lost_mode_since;

  return (
    <Card className={cn(isLost && "ring-2 ring-accent-500")}>
      <CardHeader title="Carné digital" description="QR que verifica sus vacunas; sirve para guarderías, peluquerías y viajes." />
      <div className="space-y-5 p-5">
        <div className="flex items-start gap-4">
          {/* eslint-disable-next-line @next/next/no-img-element -- private image behind the BFF */}
          <img
            src={`/api/backend/pets/${pet.id}/qr?v=${qrVersion}`}
            alt="QR del carné"
            className={cn("h-28 w-28 shrink-0 rounded-lg border border-gray-200 bg-white p-1 dark:border-gray-700", s && !s.public_profile_enabled && !isLost && "opacity-30")}
          />
          <div className="min-w-0 space-y-2">
            <Link
              href={`/app/mascotas/${pet.id}/carne`}
              className="inline-flex h-9 items-center gap-2 rounded-lg bg-brand-500 px-3 text-theme-sm font-medium text-white shadow-theme-xs hover:bg-brand-600"
            >
              Diseñar e imprimir carné
            </Link>
            {s && (
              <a href={s.public_url} target="_blank" rel="noopener" className="block text-theme-xs font-medium text-brand-600 hover:underline dark:text-brand-400">
                Ver página pública
              </a>
            )}
            <p className="text-theme-xs text-gray-500">{s ? `${s.total} ${s.total === 1 ? "escaneo" : "escaneos"}` : " "}</p>
          </div>
        </div>

        {canManage && s && (
          <>
            <label className="flex items-center justify-between gap-4">
              <span>
                <span className="block text-theme-sm font-medium text-gray-800 dark:text-white/90">Página pública activa</span>
                <span className="block text-theme-xs text-gray-500">Si la apagas, el QR no muestra nada (salvo en modo perdida).</span>
              </span>
              <input type="checkbox" className="h-4 w-4 accent-brand-500" checked={s.public_profile_enabled} onChange={(e) => visibility.mutate(e.target.checked)} disabled={visibility.isPending} />
            </label>

            <div className={cn("rounded-xl border p-4", isLost ? "border-accent-300 bg-accent-50 dark:border-accent-500/40 dark:bg-accent-500/10" : "border-gray-200 dark:border-gray-800")}>
              <p className="text-theme-sm font-semibold text-gray-800 dark:text-white/90">Modo mascota perdida</p>
              {isLost ? (
                <>
                  <p className="mt-1 text-theme-xs text-accent-800 dark:text-accent-300">
                    Activo desde {new Date(s.lost_mode_since!).toLocaleString("es-CO", { dateStyle: "medium", timeStyle: "short" })}. Quien escanee el QR verá el contacto
                    {s.public_contact_phone ? ` ${fromE164(s.public_contact_phone)}` : " del tutor"}.
                  </p>
                  <Button size="sm" variant="outline" className="mt-3" loading={lost.isPending} onClick={() => lost.mutate(false)}>
                    Ya apareció: desactivar
                  </Button>
                </>
              ) : (
                <>
                  <p className="mt-1 text-theme-xs text-gray-500">La página del QR mostrará un aviso y botones para llamar o escribir al tutor.</p>
                  <div className="mt-3 flex gap-2">
                    <Input className="h-9" type="tel" aria-label="Teléfono de contacto" placeholder={`Contacto (vacío = ${pet.owner.mobile_phone ? fromE164(pet.owner.mobile_phone) : "celular del tutor"})`} value={phone} onChange={(e) => setPhone(e.target.value)} />
                    <Button size="sm" variant="danger" loading={lost.isPending} onClick={() => lost.mutate(true)} disabled={!!pet.deceased_at}>
                      Activar
                    </Button>
                  </div>
                </>
              )}
              {lostError && <p className="mt-2 text-theme-xs text-error-500">{lostError}</p>}
            </div>
          </>
        )}

        {s && s.views.length > 0 && (
          <div>
            <p className="mb-1.5 text-theme-xs font-semibold tracking-wide text-gray-400 uppercase">Últimos escaneos</p>
            <ul className="space-y-1 text-theme-xs text-gray-600 dark:text-gray-400">
              {s.views.slice(0, 5).map((v, i) => (
                <li key={i} className="flex justify-between gap-3">
                  <span>{new Date(v.viewed_at).toLocaleString("es-CO", { dateStyle: "medium", timeStyle: "short" })}</span>
                  <span className={cn(v.in_lost_mode && "font-semibold text-accent-700 dark:text-accent-300")}>
                    {v.device}
                    {v.in_lost_mode && " · perdida"}
                  </span>
                </li>
              ))}
            </ul>
          </div>
        )}

        {canManage &&
          (confirmRegen ? (
            <div className="rounded-lg bg-warning-50 p-3 text-theme-xs text-warning-800 dark:bg-warning-500/10 dark:text-warning-300">
              <p>Los carnés ya impresos dejarán de funcionar. Úsalo si se perdió un carné o se usó mal.</p>
              <div className="mt-2 flex gap-2">
                <Button size="sm" variant="outline" onClick={() => setConfirmRegen(false)}>
                  Volver
                </Button>
                <Button size="sm" variant="danger" loading={regenerate.isPending} onClick={() => regenerate.mutate()}>
                  Generar QR nuevo
                </Button>
              </div>
            </div>
          ) : (
            <button type="button" onClick={() => setConfirmRegen(true)} className="text-theme-xs text-gray-400 hover:text-gray-700 dark:hover:text-gray-200">
              Generar un QR nuevo
            </button>
          ))}
      </div>
    </Card>
  );
}
