"use client";

import SignaturePad, { type SignaturePadHandle } from "@/components/domain/SignaturePad";
import { Checkbox } from "@/components/form/fields";
import { useRef, useState } from "react";

/**
 * The signing box for the owner on their own phone (remote link and portal):
 * pad, "I read and accept", and the button. The caller sends it.
 */
export default function SignHere({
  busy,
  error,
  before,
  onSign,
}: {
  busy: boolean;
  error?: string | null;
  before?: React.ReactNode;
  onSign: (image: string) => void;
}) {
  const pad = useRef<SignaturePadHandle>(null);
  const [hasInk, setHasInk] = useState(false);
  const [accepted, setAccepted] = useState(false);

  return (
    <form
      className="space-y-4"
      onSubmit={(e) => {
        e.preventDefault();
        const image = pad.current?.toDataUrl();
        if (image && accepted) onSign(image);
      }}
    >
      {before}
      <div>
        <p className="mb-1.5 text-theme-sm font-medium text-gray-700 dark:text-gray-300">Tu firma</p>
        <div className="relative rounded-xl border-2 border-dashed border-gray-300 bg-white dark:border-gray-600">
          <SignaturePad ref={pad} onChange={setHasInk} className="block h-48 w-full rounded-xl" />
          <span className="pointer-events-none absolute right-4 bottom-10 left-4 border-b border-gray-400" aria-hidden />
          {!hasInk && <span className="pointer-events-none absolute inset-x-0 top-1/2 -translate-y-1/2 text-center text-theme-sm text-gray-400">Firma aquí con el dedo</span>}
        </div>
        <div className="mt-1 text-right">
          <button type="button" onClick={() => pad.current?.clear()} className="text-theme-xs font-medium text-gray-500">
            Borrar y volver a firmar
          </button>
        </div>
      </div>
      <Checkbox label="Leí el documento completo, entendí su contenido y lo acepto." checked={accepted} onChange={(e) => setAccepted(e.target.checked)} />
      {error && (
        <p role="alert" className="rounded-lg bg-error-50 px-4 py-3 text-theme-sm text-error-700 dark:bg-error-500/10 dark:text-error-400">
          {error}
        </p>
      )}
      <button
        type="submit"
        disabled={busy || !hasInk || !accepted}
        className="w-full rounded-xl bg-brand-500 px-4 py-3.5 text-sm font-semibold text-white hover:bg-brand-600 disabled:opacity-50"
      >
        {busy ? "Firmando…" : "Firmar documento"}
      </button>
    </form>
  );
}
