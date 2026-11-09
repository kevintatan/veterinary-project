"use client";

import PetAvatar from "@/components/domain/PetAvatar";
import { api, ApiError } from "@/lib/api";
import { cn } from "@/utils";
import { useMutation, useQueryClient } from "@tanstack/react-query";
import { useRef } from "react";

export const PHOTO_ACCEPT = "image/jpeg,image/png,image/webp,image/heic";

/**
 * Upload / change / remove a pet's photo (the API re-encodes it). One component
 * for the pet record, the edit panel and the card designer: the photo is what
 * the printed card shows.
 */
export default function PetPhotoUploader({
  petId,
  name,
  photoUrl,
  speciesCode,
  size = "lg",
  layout = "stacked",
}: {
  petId: string;
  name: string;
  photoUrl: string | null;
  speciesCode?: string | null;
  size?: "md" | "lg";
  layout?: "stacked" | "inline";
}) {
  const queryClient = useQueryClient();
  const input = useRef<HTMLInputElement>(null);
  const refresh = () => {
    // Pet record, its card designer data and every list showing the avatar.
    queryClient.invalidateQueries({ queryKey: ["pets", petId] });
    queryClient.invalidateQueries({ queryKey: ["owners"] });
  };

  const upload = useMutation({
    mutationFn: (file: File) => {
      const body = new FormData();
      body.append("photo", file);
      return api(`pets/${petId}/photo`, { method: "POST", body });
    },
    onSuccess: refresh,
  });
  const remove = useMutation({ mutationFn: () => api(`pets/${petId}/photo`, { method: "DELETE" }), onSuccess: refresh });

  const busy = upload.isPending || remove.isPending;
  const error = upload.error instanceof ApiError ? (upload.error.field("photo") ?? upload.error.message) : null;

  return (
    <div className={cn("flex gap-3", layout === "stacked" ? "flex-col items-center" : "items-center")}>
      <button
        type="button"
        onClick={() => input.current?.click()}
        disabled={busy}
        aria-label={photoUrl ? "Cambiar foto" : "Subir foto"}
        className="group relative shrink-0 rounded-3xl focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:outline-none"
      >
        <PetAvatar photoUrl={photoUrl} speciesCode={speciesCode} name={name} size={size} />
        <span className={cn("absolute inset-0 flex items-center justify-center rounded-3xl bg-gray-900/50 text-theme-xs font-medium text-white opacity-0 transition group-hover:opacity-100", busy && "opacity-100")}>
          {upload.isPending ? "Subiendo…" : remove.isPending ? "Quitando…" : photoUrl ? "Cambiar" : "Subir"}
        </span>
      </button>

      <div className={cn("flex flex-col gap-1", layout === "stacked" && "items-center")}>
        <button
          type="button"
          onClick={() => input.current?.click()}
          disabled={busy}
          className="inline-flex w-fit items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-theme-xs font-medium text-brand-700 ring-1 ring-brand-200 ring-inset hover:bg-brand-50 disabled:opacity-60 dark:text-brand-300 dark:ring-brand-500/40 dark:hover:bg-brand-500/10"
        >
          <svg width="14" height="14" viewBox="0 0 20 20" fill="none" aria-hidden>
            <path d="M3 7.5A1.5 1.5 0 0 1 4.5 6h1.8l1.2-1.8h5l1.2 1.8h1.8A1.5 1.5 0 0 1 17 7.5v7a1.5 1.5 0 0 1-1.5 1.5h-11A1.5 1.5 0 0 1 3 14.5v-7Z" stroke="currentColor" strokeWidth="1.5" strokeLinejoin="round" />
            <circle cx="10" cy="11" r="2.8" stroke="currentColor" strokeWidth="1.5" />
          </svg>
          {photoUrl ? "Cambiar foto" : "Subir foto"}
        </button>
        {photoUrl && (
          <button type="button" onClick={() => remove.mutate()} disabled={busy} className="text-[11px] text-gray-400 hover:text-error-600">
            Quitar foto
          </button>
        )}
        {layout === "inline" && !photoUrl && <span className="text-[11px] text-gray-500">JPG, PNG o foto del celular. Se recorta en cuadrado.</span>}
        {error && <span className="max-w-48 text-[11px] text-error-500">{error}</span>}
      </div>

      <input
        ref={input}
        type="file"
        accept={PHOTO_ACCEPT}
        className="hidden"
        onChange={(e) => {
          const file = e.target.files?.[0];
          if (file) upload.mutate(file);
          e.target.value = "";
        }}
      />
    </div>
  );
}
