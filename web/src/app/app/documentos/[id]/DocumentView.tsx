"use client";

/* eslint-disable @next/next/no-img-element -- private signature images behind the BFF */
import { Skeleton } from "@/components/common/Card";
import PdfIcon from "@/components/common/PdfIcon";
import SignaturePad, { type SignaturePadHandle } from "@/components/domain/SignaturePad";
import { Checkbox, Field, Input, Textarea } from "@/components/form/fields";
import Button from "@/components/ui/button/Button";
import { Modal } from "@/components/ui/modal";
import { ApiError, api } from "@/lib/api";
import { type PetDocument, type SignerRole, STATUS_TONE } from "@/lib/documents";
import { cn } from "@/utils";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import Link from "next/link";
import { useRef, useState } from "react";
import DocumentFormPanel from "../../mascotas/[id]/DocumentFormPanel";

const ROLE_ORDER: SignerRole[] = ["owner", "vet", "witness"];

const when = (iso: string) => new Date(iso).toLocaleString("es-CO", { day: "numeric", month: "short", year: "numeric", hour: "numeric", minute: "2-digit" }).replace(/\./g, "");

/**
 * The consent as the owner reads it (same text as the PDF), with a box per
 * signer. The first signature locks the text; when everyone signed, the PDF
 * is generated and becomes the original.
 */
export default function DocumentView({ id }: { id: string }) {
  const queryClient = useQueryClient();
  const key = ["documents", id];
  const doc = useQuery({ queryKey: key, queryFn: () => api<{ data: PetDocument }>(`documents/${id}`).then((r) => r.data) });
  const [signing, setSigning] = useState<SignerRole | null>(null);
  const [editing, setEditing] = useState(false);
  const [voiding, setVoiding] = useState(false);
  const [sending, setSending] = useState(false);

  const refresh = (d: PetDocument) => {
    queryClient.setQueryData(key, d);
    queryClient.invalidateQueries({ queryKey: ["pets", d.pet_id, "documents"] });
  };

  if (!doc.data) {
    return <Skeleton className="h-[600px]" />;
  }

  const d = doc.data;
  const s = d.snapshot;
  const signed = new Map(d.signature_list.map((x) => [x.role, x]));
  const roles = ROLE_ORDER.filter((r) => s.signers[r]);
  const open = d.status.value === "draft" || d.status.value === "signing";

  return (
    <>
      <div className="mb-5 flex flex-wrap items-start justify-between gap-4">
        <div>
          <Link href={`/app/mascotas/${d.pet_id}`} className="text-theme-sm text-gray-500 hover:text-brand-600">
            ← {s.pet.name}
          </Link>
          <h1 className="mt-1 text-theme-xl font-semibold text-gray-800 dark:text-white/90">{d.title}</h1>
          <p className="mt-1 flex flex-wrap items-center gap-2 text-theme-sm text-gray-500">
            <span className={cn("rounded-full px-2 py-0.5 text-[11px] font-medium", STATUS_TONE[d.status.value])}>{d.status.label}</span>
            {d.created_by && <span>Creado por {d.created_by.name}</span>}
            {open && <span>· {d.signatures} de {d.signers} firmas</span>}
          </p>
        </div>
        <div className="flex flex-wrap gap-2">
          {d.can.edit && (
            <Button size="sm" variant="outline" onClick={() => setEditing(true)}>
              Editar
            </Button>
          )}
          <a
            href={`/api/backend/documents/${d.id}/pdf`}
            target="_blank"
            rel="noreferrer"
            className="inline-flex items-center gap-2 rounded-lg px-4 py-2.5 text-sm font-medium text-gray-700 ring-1 ring-gray-300 ring-inset hover:bg-gray-50 dark:text-gray-300 dark:ring-gray-700 dark:hover:bg-white/5"
          >
            <PdfIcon className="size-4" />
            {d.status.value === "signed" ? "PDF firmado" : "Ver PDF"}
          </a>
          {d.can.void && (
            <Button size="sm" variant="ghost" onClick={() => setVoiding(true)}>
              Anular
            </Button>
          )}
        </div>
      </div>

      {d.status.value === "signing" && (
        <Notice tone="warning">Ya hay firmas: el texto quedó bloqueado. Si hay que corregir algo, anula este documento y crea uno nuevo.</Notice>
      )}
      {d.status.value === "signed" && (
        <Notice tone="success">
          Firmado por todos el {when(d.signed_at!)}. El PDF firmado es el original y no cambia.
          <span className="mt-1 block font-mono text-[11px] break-all opacity-80">Huella del contenido (SHA-256): {d.content_hash}</span>
        </Notice>
      )}
      {d.status.value === "voided" && <Notice tone="error">Anulado el {when(d.voided_at!)}. Motivo: {d.void_reason}</Notice>}

      {/* The paper: same content and order as the PDF. */}
      <article className="mx-auto max-w-3xl rounded-2xl border border-gray-200 bg-white px-6 py-8 shadow-theme-xs sm:px-10 dark:border-gray-800 dark:bg-gray-900">
        <header className="flex flex-wrap items-start justify-between gap-3 border-b-2 border-brand-500 pb-3">
          <div>
            <p className="text-lg font-bold text-brand-600 dark:text-brand-400">{s.clinic.name}</p>
            <p className="text-theme-xs text-gray-500">{[s.clinic.legal_name, s.clinic.tax_id && `NIT ${s.clinic.tax_id}`, s.clinic.address].filter(Boolean).join(" · ")}</p>
          </div>
          <p className="text-theme-xs text-gray-500">{s.date}</p>
        </header>

        <h2 className="mt-6 text-center text-lg font-semibold text-gray-900 dark:text-white">{s.title}</h2>

        <dl className="mt-5 grid gap-x-6 gap-y-3 rounded-xl bg-gray-50 p-4 text-theme-sm sm:grid-cols-2 dark:bg-white/3">
          <Fact term="Paciente">{[s.pet.name, s.pet.species, s.pet.breed].filter(Boolean).join(" · ")}</Fact>
          <Fact term="Sexo · Edad · Peso">{[s.pet.sex, s.pet.age, s.pet.weight].filter(Boolean).join(" · ")}</Fact>
          <Fact term="Tutor">
            {s.owner.name} · {s.owner.document}
          </Fact>
          <Fact term="Médico veterinario">
            {s.vet.name} · T.P. {s.vet.license}
          </Fact>
        </dl>

        <div className="mt-6 space-y-3 text-[15px] leading-relaxed text-gray-800 dark:text-gray-200">
          {s.blocks.map((b, i) =>
            b.type === "list" ? (
              <ul key={i} className="list-disc space-y-2 pl-5">
                {b.items.map((item, j) => (
                  <li key={j}>{item}</li>
                ))}
              </ul>
            ) : (
              <p key={i}>{b.text}</p>
            ),
          )}
          <p>En constancia se firma electrónicamente el {s.date}.</p>
        </div>

        <div className={cn("mt-10 grid gap-6", roles.length === 3 ? "sm:grid-cols-3" : "sm:grid-cols-2")}>
          {roles.map((role) => {
            const sig = signed.get(role);
            const signer = s.signers[role]!;
            const name = role === "owner" ? s.owner.name : role === "vet" ? s.vet.name : sig?.name;

            return (
              <div key={role} className="flex flex-col">
                <div className="flex h-24 items-end justify-center">
                  {sig ? (
                    <img src={`/api/backend/${sig.image_url}`} alt={`Firma de ${sig.name}`} className="max-h-24 max-w-full object-contain dark:invert" />
                  ) : open ? (
                    <div className="flex flex-col items-center">
                      <SignButton role={role} doc={d} onClick={() => setSigning(role)} />
                      {role === "owner" && d.can.send_remote && (
                        <button type="button" onClick={() => setSending(true)} className="mb-1 text-theme-xs font-medium text-gray-500 hover:text-brand-600">
                          {d.remote?.active ? "Reenviar enlace" : "o enviar para firmar a distancia"}
                        </button>
                      )}
                    </div>
                  ) : null}
                </div>
                <div className="mt-1 border-t border-gray-800 pt-2 text-theme-xs dark:border-gray-400">
                  <p className="font-semibold text-gray-800 dark:text-white/90">{name ?? "—"}</p>
                  <p className="text-gray-500">
                    {signer.label}
                    {sig?.document && ` · ${sig.document}`}
                  </p>
                  <p className="text-gray-400">{sig ? `Firmó el ${when(sig.signed_at)}` : "Pendiente"}</p>
                  {sig && sig.method !== "in_person" && <p className="text-gray-400">{sig.method === "portal" ? "En el portal del tutor" : "A distancia, por enlace"}</p>}
                  {!sig && role === "owner" && d.remote && open && <RemoteStatus doc={d} onChanged={refresh} />}
                </div>
              </div>
            );
          })}
        </div>
      </article>

      {signing && <SignModal doc={d} role={signing} onClose={() => setSigning(null)} onSigned={refresh} />}
      {editing && <DocumentFormPanel petId={d.pet_id} editing={d} onClose={() => setEditing(false)} onSaved={refresh} />}
      {voiding && <VoidModal doc={d} onClose={() => setVoiding(false)} onVoided={refresh} />}
      {sending && <SendRemoteModal doc={d} onClose={() => setSending(false)} onSent={refresh} />}
    </>
  );
}

function SignButton({ role, doc, onClick }: { role: SignerRole; doc: PetDocument; onClick: () => void }) {
  if (role === "vet" && !doc.can.sign_as_vet) {
    return <p className="pb-2 text-center text-theme-xs text-gray-400">Firma {doc.snapshot.vet.name} desde su sesión</p>;
  }

  return (
    <button
      type="button"
      onClick={onClick}
      className="mb-2 rounded-lg border-2 border-dashed border-brand-300 px-4 py-2.5 text-theme-sm font-semibold text-brand-600 hover:border-brand-500 hover:bg-brand-25 dark:border-brand-500/40 dark:text-brand-400 dark:hover:bg-brand-500/10"
    >
      {role === "owner" ? "Firmar (tutor)" : role === "vet" ? "Firmar como veterinario" : "Firmar (testigo)"}
    </button>
  );
}

/** Big pad, made for a tablet passed to the owner across the counter. */
function SignModal({ doc, role, onClose, onSigned }: { doc: PetDocument; role: SignerRole; onClose: () => void; onSigned: (d: PetDocument) => void }) {
  const pad = useRef<SignaturePadHandle>(null);
  const [hasInk, setHasInk] = useState(false);
  const [accepted, setAccepted] = useState(false);
  const [name, setName] = useState("");
  const [docNumber, setDocNumber] = useState("");
  const s = doc.snapshot;
  const who = role === "owner" ? `${s.owner.name} · ${s.owner.document}` : role === "vet" ? `${s.vet.name} · T.P. ${s.vet.license}` : null;

  const sign = useMutation({
    mutationFn: () =>
      api<{ data: PetDocument }>(`documents/${doc.id}/signatures`, {
        method: "POST",
        body: { role, image: pad.current?.toDataUrl(), accepted, signer_name: name || null, signer_document: docNumber || null },
      }).then((r) => r.data),
    onSuccess: (d) => {
      onSigned(d);
      onClose();
    },
  });

  const error = sign.error instanceof ApiError ? sign.error : null;
  const message = error && (error.field("image") ?? error.field("role") ?? error.field("accepted") ?? (Object.keys(error.errors).length ? null : error.message));

  return (
    <Modal isOpen onClose={onClose} className="m-4 w-full max-w-2xl rounded-2xl bg-white p-6 dark:bg-gray-900 [&>h2]:pr-12 [&>p:first-of-type]:pr-12">
      <h2 className="text-lg font-semibold text-gray-800 dark:text-white/90">{s.signers[role]?.label}</h2>
      {who && <p className="text-theme-sm text-gray-500">{who}</p>}

      {role === "witness" && (
        <div className="mt-4 grid gap-3 sm:grid-cols-2">
          <Field label="Nombre del testigo" htmlFor="w-name" error={error?.field("signer_name")}>
            <Input id="w-name" value={name} onChange={(e) => setName(e.target.value)} autoComplete="off" />
          </Field>
          <Field label="Documento" htmlFor="w-doc" error={error?.field("signer_document")}>
            <Input id="w-doc" value={docNumber} onChange={(e) => setDocNumber(e.target.value)} placeholder="CC 1098…" autoComplete="off" />
          </Field>
        </div>
      )}

      <div className="mt-4">
        <div className="relative rounded-xl border-2 border-dashed border-gray-300 bg-gray-50 dark:border-gray-700 dark:bg-white">
          <SignaturePad ref={pad} onChange={setHasInk} className="block h-56 w-full cursor-crosshair rounded-xl" />
          <span className="pointer-events-none absolute right-4 bottom-12 left-4 border-b border-gray-400" aria-hidden />
          {!hasInk && <span className="pointer-events-none absolute inset-x-0 top-1/2 -translate-y-1/2 text-center text-theme-sm text-gray-400">Firma aquí con el dedo o el mouse</span>}
        </div>
        <div className="mt-1 flex justify-end">
          <button type="button" onClick={() => pad.current?.clear()} className="text-theme-xs font-medium text-gray-500 hover:text-gray-800">
            Borrar y volver a firmar
          </button>
        </div>
      </div>

      <div className="mt-3">
        <Checkbox
          label={role === "witness" ? "Presencié la lectura y la firma de este documento." : "Leí el documento completo, entendí su contenido y lo acepto."}
          checked={accepted}
          onChange={(e) => setAccepted(e.target.checked)}
        />
      </div>

      {message && <p className="mt-3 text-theme-sm text-error-600">{message}</p>}

      <div className="mt-5 flex justify-end gap-2">
        <Button variant="outline" size="sm" onClick={onClose}>
          Cancelar
        </Button>
        <Button size="sm" loading={sign.isPending} disabled={!hasInk || !accepted} onClick={() => sign.mutate()}>
          Firmar
        </Button>
      </div>
    </Modal>
  );
}

/** Where the remote link stands, with a way to cancel it. */
function RemoteStatus({ doc, onChanged }: { doc: PetDocument; onChanged: (d: PetDocument) => void }) {
  const r = doc.remote!;
  const revoke = useMutation({
    mutationFn: () => api<{ data: PetDocument }>(`documents/${doc.id}/remote`, { method: "DELETE" }).then((x) => x.data),
    onSuccess: onChanged,
  });
  const channel = { whatsapp: "por WhatsApp", email: "por correo", link: "por enlace" }[r.channel];

  return (
    <p className={cn("mt-1", r.active ? "text-warning-700 dark:text-warning-400" : "text-error-600")}>
      {r.active ? `Enviado ${channel} · vence ${when(r.expires_at)}` : r.attempts >= 5 ? "Enlace bloqueado por intentos fallidos" : "El enlace venció"}
      {" · "}
      <button type="button" onClick={() => revoke.mutate()} disabled={revoke.isPending} className="font-medium underline">
        Cancelar enlace
      </button>
    </p>
  );
}

/**
 * Send to sign from the owner's phone: the clinic's own WhatsApp with the text
 * ready (free), email, or copy the link. Sending locks the text.
 */
function SendRemoteModal({ doc, onClose, onSent }: { doc: PetDocument; onClose: () => void; onSent: (d: PetDocument) => void }) {
  const [copied, setCopied] = useState(false);
  const send = useMutation({
    mutationFn: (channel: "whatsapp" | "email" | "link") =>
      api<{ data: { url: string; whatsapp_url: string | null; document: PetDocument } }>(`documents/${doc.id}/remote`, { method: "POST", body: { channel } }).then((r) => ({ channel, ...r.data })),
    onSuccess: async ({ channel, url, whatsapp_url, document }) => {
      onSent(document);
      if (channel === "whatsapp" && whatsapp_url) window.open(whatsapp_url, "_blank", "noopener");
      if (channel === "link") {
        await navigator.clipboard.writeText(url).catch(() => undefined);
        setCopied(true);
        return;
      }
      onClose();
    },
  });
  const error = send.error instanceof ApiError ? (send.error.field("channel") ?? send.error.message) : null;
  const option = "flex w-full items-start gap-3 rounded-xl border border-gray-200 px-4 py-3 text-left hover:border-brand-300 hover:bg-brand-25 disabled:cursor-not-allowed disabled:opacity-50 dark:border-gray-800 dark:hover:bg-brand-500/10";

  return (
    <Modal isOpen onClose={onClose} className="m-4 w-full max-w-md rounded-2xl bg-white p-6 dark:bg-gray-900 [&>h2]:pr-12 [&>p:first-of-type]:pr-12">
      <h2 className="text-lg font-semibold text-gray-800 dark:text-white/90">Enviar para firmar a distancia</h2>
      <p className="mt-1 text-theme-sm text-gray-500">
        {doc.snapshot.owner.name} lo abre en su celular, confirma los últimos 4 dígitos de su documento y firma. El enlace sirve una vez y vence en 48 horas. Al enviarlo, el texto queda bloqueado.
      </p>
      <div className="mt-4 space-y-2">
        <button type="button" className={option} disabled={!doc.owner_contact.has_phone || send.isPending} onClick={() => send.mutate("whatsapp")}>
          <span>
            <span className="block text-theme-sm font-semibold text-gray-800 dark:text-white/90">Por WhatsApp</span>
            <span className="block text-theme-xs text-gray-500">{doc.owner_contact.has_phone ? "Abre el WhatsApp de la clínica con el mensaje listo (gratis)." : "El tutor no tiene celular registrado."}</span>
          </span>
        </button>
        <button type="button" className={option} disabled={!doc.owner_contact.has_email || send.isPending} onClick={() => send.mutate("email")}>
          <span>
            <span className="block text-theme-sm font-semibold text-gray-800 dark:text-white/90">Por correo</span>
            <span className="block text-theme-xs text-gray-500">{doc.owner_contact.has_email ? "Le llega un correo con el botón para firmar." : "El tutor no tiene correo registrado."}</span>
          </span>
        </button>
        <button type="button" className={option} disabled={send.isPending} onClick={() => send.mutate("link")}>
          <span>
            <span className="block text-theme-sm font-semibold text-gray-800 dark:text-white/90">{copied ? "¡Enlace copiado!" : "Copiar enlace"}</span>
            <span className="block text-theme-xs text-gray-500">Para enviarlo por otro medio. Si el tutor tiene el portal, también le aparece ahí.</span>
          </span>
        </button>
      </div>
      {error && <p className="mt-3 text-theme-sm text-error-600">{error}</p>}
      <div className="mt-5 flex justify-end">
        <Button variant="outline" size="sm" onClick={onClose}>
          {copied ? "Listo" : "Cancelar"}
        </Button>
      </div>
    </Modal>
  );
}

function VoidModal({ doc, onClose, onVoided }: { doc: PetDocument; onClose: () => void; onVoided: (d: PetDocument) => void }) {
  const [reason, setReason] = useState("");
  const voidDoc = useMutation({
    mutationFn: () => api<{ data: PetDocument }>(`documents/${doc.id}/void`, { method: "POST", body: { reason } }).then((r) => r.data),
    onSuccess: (d) => {
      onVoided(d);
      onClose();
    },
  });
  const error = voidDoc.error instanceof ApiError ? (voidDoc.error.field("reason") ?? voidDoc.error.message) : null;

  return (
    <Modal isOpen onClose={onClose} className="m-4 w-full max-w-md rounded-2xl bg-white p-6 dark:bg-gray-900 [&>h2]:pr-12 [&>p:first-of-type]:pr-12">
      <h2 className="text-lg font-semibold text-gray-800 dark:text-white/90">Anular documento</h2>
      <p className="mt-1 text-theme-sm text-gray-500">No se borra: queda marcado como anulado, con el motivo, y se conserva el PDF.</p>
      <Field label="Motivo" htmlFor="void-reason" error={error ?? undefined} className="mt-4">
        <Textarea id="void-reason" rows={3} value={reason} onChange={(e) => setReason(e.target.value)} placeholder="Ej.: Se firmó con un dato equivocado." autoFocus />
      </Field>
      <div className="mt-5 flex justify-end gap-2">
        <Button variant="outline" size="sm" onClick={onClose}>
          Cancelar
        </Button>
        <Button variant="danger" size="sm" loading={voidDoc.isPending} disabled={reason.trim().length < 5} onClick={() => voidDoc.mutate()}>
          Anular
        </Button>
      </div>
    </Modal>
  );
}

function Notice({ tone, children }: { tone: "warning" | "success" | "error"; children: React.ReactNode }) {
  const tones = {
    warning: "border-warning-200 bg-warning-50 text-warning-800 dark:border-warning-500/30 dark:bg-warning-500/10 dark:text-warning-300",
    success: "border-success-200 bg-success-50 text-success-800 dark:border-success-500/30 dark:bg-success-500/10 dark:text-success-300",
    error: "border-error-200 bg-error-50 text-error-800 dark:border-error-500/30 dark:bg-error-500/10 dark:text-error-300",
  };
  return <div className={cn("mx-auto mb-4 max-w-3xl rounded-xl border px-4 py-3 text-theme-sm", tones[tone])}>{children}</div>;
}

function Fact({ term, children }: { term: string; children: React.ReactNode }) {
  return (
    <div className="min-w-0">
      <dt className="text-theme-xs text-gray-500">{term}</dt>
      <dd className="font-medium text-gray-800 dark:text-white/90">{children}</dd>
    </div>
  );
}
