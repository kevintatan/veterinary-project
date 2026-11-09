"use client";

/* eslint-disable @next/next/no-img-element -- clinic logo behind the BFF */
import { Skeleton } from "@/components/common/Card";
import Button from "@/components/ui/button/Button";
import { ApiError, api } from "@/lib/api";
import type { DocumentField } from "@/lib/documents";
import type { ClinicProfile } from "@/lib/settings";
import { cn } from "@/utils";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Fragment, useEffect, useLayoutEffect, useRef, useState } from "react";

/** One paragraph or bullet list of a consent; `when` = only if that case field is filled. */
type Block = { text?: string; list?: string[]; when?: string };

type TemplateType = {
  code: string;
  name: string;
  short: string;
  fields: DocumentField[];
  signers: { role: string; label: string; required: boolean }[];
  body: Block[];
  customized: boolean;
  version: number | null;
  base_changed: boolean;
  updated_by: string | null;
};

type Templates = { placeholders: { key: string; label: string; example: string }[]; types: TemplateType[] };

/** A datum that fills itself: shown as «Label» while editing, as an example value on the page. */
type Token = { key: string; label: string; example: string; kind: "auto" | "case" };

/** Editing state. `value` is human text: data appear as «Label»; lists are one line per bullet. */
type Draft = { id: number; kind: "text" | "list"; value: string; when: string };

let nextId = 1;
const TOKEN = /\{\{\s*([a-z_.]+)\s*\}\}/g;
const LABEL = /«([^«»\n]+)»/g;

/**
 * The clinic rewords its consents (D4), editing directly on the page: the
 * same letter-size sheet, letterhead and typography as the signed PDF. Click
 * a paragraph to edit it; data appear as «Nombre del tutor» while editing and
 * as example values (highlighted) on the page. Only the text changes: the
 * case fields and who signs stay Zooetia's.
 */
export default function DocumentsSection() {
  const queryClient = useQueryClient();
  const templates = useQuery({ queryKey: ["document-templates"], queryFn: () => api<{ data: Templates }>("document-templates").then((r) => r.data) });
  const clinic = useQuery({ queryKey: ["clinic"], queryFn: () => api<{ data: ClinicProfile }>("clinic").then((r) => r.data) });
  const [code, setCode] = useState("euthanasia");
  // Kept here: saving remounts the editor (new version), which would lose its own state.
  const [notice, setNotice] = useState<string | null>(null);
  const type = templates.data?.types.find((t) => t.code === code);

  if (!templates.data || !type) {
    return <Skeleton className="h-[700px]" />;
  }

  return (
    <Editor
      key={`${type.code}-${type.version ?? 0}`}
      type={type}
      types={templates.data.types}
      onSelect={(c) => (setCode(c), setNotice(null))}
      placeholders={templates.data.placeholders}
      clinic={clinic.data}
      notice={notice}
      onSaved={(d, message) => {
        queryClient.setQueryData(["document-templates"], d);
        setNotice(message);
      }}
    />
  );
}

function Editor({
  type,
  types,
  onSelect,
  placeholders,
  clinic,
  notice,
  onSaved,
}: {
  type: TemplateType;
  types: TemplateType[];
  onSelect: (code: string) => void;
  placeholders: Templates["placeholders"];
  clinic: ClinicProfile | undefined;
  notice: string | null;
  onSaved: (d: Templates, message: string) => void;
}) {
  const tokens: Token[] = [
    ...type.fields.map((f) => ({ key: `campo.${f.key}`, label: f.label, example: fieldSample(f), kind: "case" as const })),
    ...placeholders.map((p) => ({ key: p.key, label: p.label, example: p.example, kind: "auto" as const })),
  ];
  const byKey = new Map(tokens.map((t) => [t.key, t]));
  const byLabel = new Map(tokens.map((t) => [t.label, t]));

  const friendly = (raw: string) => raw.replace(TOKEN, (m, key: string) => (byKey.has(key) ? `«${byKey.get(key)!.label}»` : m));
  const raw = (text: string) => text.replace(LABEL, (m, label: string) => (byLabel.has(label) ? `{{${byLabel.get(label)!.key}}}` : m));
  const unknown = (text: string) => [...text.matchAll(LABEL)].map((m) => m[1]).filter((l) => !byLabel.has(l));

  const toDraft = (body: Block[]): Draft[] =>
    body.map((b) => ({ id: nextId++, kind: b.list ? "list" : "text", value: friendly(b.list ? b.list.join("\n") : (b.text ?? "")), when: b.when ?? "" }));
  const toBody = (draft: Draft[]): Block[] =>
    draft.map((d) => ({
      ...(d.kind === "list" ? { list: raw(d.value).split("\n").map((l) => l.trim()).filter(Boolean) } : { text: raw(d.value).trim() }),
      ...(d.when ? { when: d.when } : {}),
    }));

  const [draft, setDraft] = useState<Draft[]>(() => toDraft(type.body));
  const [editing, setEditing] = useState<number | null>(null);
  const [showExamples, setShowExamples] = useState(true);
  const areas = useRef(new Map<number, HTMLTextAreaElement>());
  const dirty = JSON.stringify(toBody(draft)) !== JSON.stringify(type.body.map((b) => ({ ...(b.list ? { list: b.list } : { text: b.text?.trim() }), ...(b.when ? { when: b.when } : {}) })));
  const unknownByBlock = new Map(draft.map((d) => [d.id, unknown(d.value)]));
  const hasUnknown = [...unknownByBlock.values()].some((u) => u.length > 0);

  useEffect(() => {
    if (!dirty) return;
    const warn = (e: BeforeUnloadEvent) => e.preventDefault();
    window.addEventListener("beforeunload", warn);
    return () => window.removeEventListener("beforeunload", warn);
  }, [dirty]);

  const save = useMutation({
    mutationFn: () => api<{ data: Templates }>(`document-templates/${type.code}`, { method: "PUT", body: { body: toBody(draft) } }).then((r) => r.data),
    onSuccess: (d) => onSaved(d, "Guardado. Aplica a los documentos nuevos."),
  });
  const restore = useMutation({
    mutationFn: () => api<{ data: Templates }>(`document-templates/${type.code}`, { method: "DELETE" }).then((r) => r.data),
    onSuccess: (d) => onSaved(d, "Se restauró el texto de Zooetia para los documentos nuevos."),
  });
  const error = save.error instanceof ApiError ? save.error : null;

  const update = (id: number, patch: Partial<Draft>) => setDraft((d) => d.map((b) => (b.id === id ? { ...b, ...patch } : b)));
  const move = (index: number, delta: number) =>
    setDraft((d) => {
      const next = [...d];
      const [item] = next.splice(index, 1);
      next.splice(index + delta, 0, item);
      return next;
    });
  const add = (index: number, kind: Draft["kind"]) => {
    const block = { id: nextId++, kind, value: "", when: "" };
    setDraft((d) => [...d.slice(0, index), block, ...d.slice(index)]);
    setEditing(block.id);
  };

  /** Insert «Label» where the cursor is (the box that has the cursor right now, else the one being edited). */
  const insert = (token: Token) => {
    const active = [...areas.current.entries()].find(([, el]) => el === document.activeElement)?.[0] ?? editing;
    const el = active !== null && active !== undefined ? areas.current.get(active) : undefined;
    if (!el || active === null || active === undefined) return;
    const text = `«${token.label}»`;
    const start = el.selectionStart ?? el.value.length;
    const end = el.selectionEnd ?? start;
    update(active, { value: el.value.slice(0, start) + text + el.value.slice(end) });
    requestAnimationFrame(() => {
      el.focus();
      el.setSelectionRange(start + text.length, start + text.length);
    });
  };

  const optionalFields = type.fields.filter((f) => !f.required);
  const sample = (key: string) => byKey.get(key)?.example ?? "";

  return (
    <div className="space-y-4">
      {/* Toolbar: which document, its state, and the actions. */}
      <div className="sticky top-[76px] z-20 -mx-1 flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-gray-200 bg-white/95 px-4 py-3 shadow-theme-xs backdrop-blur dark:border-gray-800 dark:bg-gray-900/95">
        <div className="flex flex-wrap gap-1.5">
          {types.map((t) => (
            <button
              key={t.code}
              type="button"
              onClick={() => onSelect(t.code)}
              className={cn(
                "rounded-full px-3 py-1.5 text-theme-sm font-medium",
                t.code === type.code ? "bg-gray-900 text-white dark:bg-white dark:text-gray-900" : "bg-gray-100 text-gray-600 hover:bg-gray-200 dark:bg-white/5 dark:text-gray-300",
              )}
            >
              {t.short}
              {t.customized && <span className="ml-1.5 text-[11px] opacity-70">· editado</span>}
            </button>
          ))}
        </div>
        <div className="flex flex-wrap items-center gap-2">
          {notice && !dirty && <span className="text-theme-xs text-success-700 dark:text-success-400">{notice}</span>}
          {type.customized && !dirty && (
            <Button size="sm" variant="ghost" loading={restore.isPending} onClick={() => restore.mutate()}>
              Restaurar texto de Zooetia
            </Button>
          )}
          {dirty && (
            <Button size="sm" variant="ghost" onClick={() => (setDraft(toDraft(type.body)), setEditing(null))}>
              Descartar cambios
            </Button>
          )}
          <Button size="sm" loading={save.isPending} disabled={!dirty || hasUnknown} onClick={() => save.mutate()}>
            Guardar texto
          </Button>
        </div>
      </div>

      <div className="grid gap-5 xl:grid-cols-[minmax(0,1fr)_260px]">
        {/* The sheet: same layout as the signed PDF. */}
        <div className="min-w-0 overflow-x-auto rounded-2xl bg-gray-100 p-3 sm:p-6 dark:bg-white/3">
          <div
            className="mx-auto min-h-[1000px] w-full max-w-[816px] bg-white px-6 py-10 text-[#101828] shadow-theme-lg ring-1 ring-black/5 sm:px-14 sm:py-12"
            style={{ fontFamily: '"DejaVu Sans", Verdana, Geneva, ui-sans-serif, sans-serif', fontSize: 13.5, lineHeight: 1.65 }}
          >
            <Letterhead clinic={clinic} />
            <h2 className="mt-6 text-center text-[17px] font-bold">{type.name}</h2>
            <p className="text-center text-[11px] text-[#475467]">
              Paciente: {sample("mascota")} · Tutor: {sample("tutor")}
            </p>
            <Facts sample={sample} />

            <div className="mt-4">
              <InsertBar onAdd={(kind) => add(0, kind)} />
              {draft.map((b, i) => (
                <Fragment key={b.id}>
                  <BlockRow
                    block={b}
                    editing={editing === b.id}
                    onEdit={() => setEditing(b.id)}
                    onDone={() => setEditing((e) => (e === b.id ? null : e))}
                    onChange={(value) => update(b.id, { value })}
                    onWhen={(when) => update(b.id, { when })}
                    onUp={i > 0 ? () => move(i, -1) : undefined}
                    onDown={i < draft.length - 1 ? () => move(i, 1) : undefined}
                    onDelete={() => setDraft((d) => d.filter((x) => x.id !== b.id))}
                    register={(el) => (el ? areas.current.set(b.id, el) : areas.current.delete(b.id))}
                    render={(text) => <Rendered text={raw(text)} byKey={byKey} showExamples={showExamples} />}
                    optionalFields={optionalFields}
                    error={
                      (unknownByBlock.get(b.id)?.length
                        ? `No existe el dato ${unknownByBlock.get(b.id)!.map((l) => `«${l}»`).join(", ")}. Usa los de la lista de datos.`
                        : null) ??
                      error?.field(`body.${i}`) ??
                      error?.field(`body.${i}.text`) ??
                      error?.field(`body.${i}.list.0`)
                    }
                  />
                  <InsertBar onAdd={(kind) => add(i + 1, kind)} />
                </Fragment>
              ))}
              <p className="mt-1 text-justify">En constancia se firma electrónicamente el {sample("fecha")}.</p>
            </div>

            <Signatures signers={type.signers} sample={sample} />

            <p className="mt-14 border-t border-[#e4e7ec] pt-2 text-[9.5px] text-[#667085]">
              {clinic?.name ?? sample("clinica")} · Documento firmado electrónicamente en Zooetia (Ley 527 de 1999). Información confidencial del paciente y su tutor (Ley 1581 de 2012).
            </p>
          </div>
        </div>

        {/* Side panel: data to insert, display mode, help. */}
        <aside className="space-y-4 xl:sticky xl:top-[150px] xl:self-start">
          <div className="rounded-2xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
            <p className="text-theme-sm font-semibold text-gray-800 dark:text-white/90">{type.customized ? `Texto de la clínica · versión ${type.version}` : "Texto de Zooetia"}</p>
            {type.customized && type.updated_by && <p className="text-theme-xs text-gray-500">Editado por {type.updated_by}</p>}
            {type.base_changed && <p className="mt-2 rounded-lg bg-brand-25 px-3 py-2 text-theme-xs text-brand-800 dark:bg-brand-500/10 dark:text-brand-300">Zooetia actualizó su texto base después de tu última edición.</p>}
            {error?.field("body") && <p className="mt-2 text-theme-xs text-error-600">{error.field("body")}</p>}
            {hasUnknown && <p className="mt-2 text-theme-xs text-error-600">Hay datos que no existen (en rojo en el documento). Corrígelos para poder guardar.</p>}
            <div className="mt-3 inline-flex rounded-lg bg-gray-100 p-0.5 text-theme-xs dark:bg-white/5">
              {[
                [true, "Con datos de ejemplo"],
                [false, "Con etiquetas"],
              ].map(([value, label]) => (
                <button
                  key={String(value)}
                  type="button"
                  onClick={() => setShowExamples(value as boolean)}
                  className={cn("rounded-md px-2.5 py-1 font-medium", showExamples === value ? "bg-white text-gray-900 shadow-theme-xs dark:bg-gray-800 dark:text-white" : "text-gray-500")}
                >
                  {label as string}
                </button>
              ))}
            </div>
          </div>

          <div className="rounded-2xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
            <p className="text-theme-sm font-semibold text-gray-800 dark:text-white/90">Datos que se llenan solos</p>
            <p className="mt-0.5 text-theme-xs text-gray-500">{editing ? "Haz clic en un dato para insertarlo donde está el cursor." : "Haz clic en un párrafo del documento para editarlo; luego inserta datos desde aquí."}</p>
            <TokenGroup title="De este caso" tokens={tokens.filter((t) => t.kind === "case")} onPick={insert} disabled={editing === null} />
            <TokenGroup title="Automáticos" tokens={tokens.filter((t) => t.kind === "auto")} onPick={insert} disabled={editing === null} />
          </div>

          <p className="px-1 text-theme-xs text-gray-500">
            Los cambios aplican a los <b>documentos nuevos</b>; los ya creados o firmados conservan su texto. Conviene que un abogado revise la versión que use la clínica.
          </p>
        </aside>
      </div>
    </div>
  );
}

/** A paragraph on the sheet: rendered like the PDF; click to edit in place. */
function BlockRow({
  block,
  editing,
  onEdit,
  onDone,
  onChange,
  onWhen,
  onUp,
  onDown,
  onDelete,
  register,
  render,
  optionalFields,
  error,
}: {
  block: Draft;
  editing: boolean;
  onEdit: () => void;
  onDone: () => void;
  onChange: (value: string) => void;
  onWhen: (when: string) => void;
  onUp?: () => void;
  onDown?: () => void;
  onDelete: () => void;
  register: (el: HTMLTextAreaElement | null) => void;
  render: (text: string) => React.ReactNode;
  optionalFields: DocumentField[];
  error: string | null | undefined;
}) {
  const area = useRef<HTMLTextAreaElement | null>(null);
  const keep = (e: React.MouseEvent) => e.preventDefault(); // toolbar clicks keep the cursor in the text

  // Grow with the text: no scrollbars, the paragraph looks like the paragraph.
  useLayoutEffect(() => {
    const el = area.current;
    if (!el) return;
    el.style.height = "0px";
    el.style.height = `${el.scrollHeight}px`;
  }, [block.value, editing]);

  useEffect(() => {
    if (editing && area.current && document.activeElement !== area.current) {
      area.current.focus();
      area.current.setSelectionRange(area.current.value.length, area.current.value.length);
    }
  }, [editing]);

  const condition = optionalFields.find((f) => f.key === block.when)?.label;
  const tools = (
    <>
        <button type="button" onMouseDown={keep} onClick={onUp} disabled={!onUp} className="rounded px-1.5 py-0.5 hover:bg-gray-100 disabled:opacity-30" aria-label="Subir">
          ↑
        </button>
        <button type="button" onMouseDown={keep} onClick={onDown} disabled={!onDown} className="rounded px-1.5 py-0.5 hover:bg-gray-100 disabled:opacity-30" aria-label="Bajar">
          ↓
        </button>
        {optionalFields.length > 0 && (
          <select
            value={block.when}
            onChange={(e) => onWhen(e.target.value)}
            className="max-w-44 rounded border-0 bg-transparent py-0.5 text-[11px] focus:ring-1 focus:ring-brand-300"
            aria-label="Cuándo se muestra"
            title="Cuándo se muestra este párrafo"
          >
            <option value="">Siempre</option>
            {optionalFields.map((f) => (
              <option key={f.key} value={f.key}>
                Si se llena: {f.label}
              </option>
            ))}
          </select>
        )}
        <button type="button" onMouseDown={keep} onClick={onDelete} className="rounded px-1.5 py-0.5 text-error-600 hover:bg-error-50">
          Eliminar
        </button>
    </>
  );
  const empty = block.value.trim() === "";

  return (
    <div className={cn("group relative -mx-3 rounded-lg px-3 py-1", editing ? "bg-brand-25 ring-2 ring-brand-400" : "hover:bg-gray-50 hover:ring-1 hover:ring-gray-200", error && "ring-2 ring-error-400")}>
      {/* Tools: floating in the margin on hover; while editing, at the bottom so they never cover the text. */}
      {!editing && (
        <div className="absolute -top-7 right-2 z-10 hidden items-center gap-0.5 rounded-lg border border-gray-200 bg-white px-1 py-0.5 text-[11px] text-gray-600 shadow-theme-xs group-hover:flex">{tools}</div>
      )}

      {condition && <p className="mb-0.5 text-[10.5px] font-medium text-[#b54708]">Solo si se llena «{condition}»</p>}

      {editing ? (
        <>
          <textarea
            ref={(el) => {
              area.current = el;
              register(el);
            }}
            value={block.value}
            onChange={(e) => onChange(e.target.value)}
            onBlur={onDone}
            onKeyDown={(e) => e.key === "Escape" && (e.currentTarget.blur(), e.preventDefault())}
            placeholder={block.kind === "list" ? "Una viñeta por línea" : "Escribe el párrafo"}
            className="block w-full resize-none overflow-hidden bg-transparent p-0 text-left outline-none placeholder:text-gray-400" /* justified only on the page: in the box it stretches short lines */
            style={{ font: "inherit", lineHeight: "inherit", color: "inherit" }}
            aria-label={block.kind === "list" ? "Viñetas, una por línea" : "Párrafo"}
          />
          <div className="mt-2 flex flex-wrap items-center justify-between gap-2 border-t border-brand-200 pt-1.5" style={{ fontFamily: "var(--font-jakarta), sans-serif" }}>
            <p className="text-[10.5px] text-brand-700">
              {block.kind === "list" ? "Una viñeta por línea · " : ""}Los datos aparecen como «Etiqueta» · Esc o clic afuera para terminar
            </p>
            <div className="flex items-center gap-0.5 text-[11px] text-gray-600">{tools}</div>
          </div>
        </>
      ) : (
        <div role="button" tabIndex={0} onClick={onEdit} onKeyDown={(e) => (e.key === "Enter" || e.key === " ") && (e.preventDefault(), onEdit())} className="cursor-text text-justify" title="Clic para editar">
          {empty ? (
            <span className="text-gray-400 italic">{block.kind === "list" ? "Viñetas vacías: clic para escribir" : "Párrafo vacío: clic para escribir"}</span>
          ) : block.kind === "list" ? (
            <ul className="list-disc space-y-1 pl-5">
              {block.value
                .split("\n")
                .filter((l) => l.trim())
                .map((line, j) => (
                  <li key={j}>{render(line)}</li>
                ))}
            </ul>
          ) : (
            render(block.value)
          )}
        </div>
      )}
      {error && (
        <p className="mt-1 text-[11px] text-error-600" style={{ fontFamily: "var(--font-jakarta), sans-serif" }}>
          {error}
        </p>
      )}
    </div>
  );
}

/** Text with its data highlighted: example values (green = automatic, coral = case) or labels. Unknown data in red. */
function Rendered({ text, byKey, showExamples }: { text: string; byKey: Map<string, Token>; showExamples: boolean }) {
  const parts = text.split(/(\{\{\s*[a-z_.]+\s*\}\}|«[^«»\n]+»)/g);

  return (
    <>
      {parts.map((part, i) => {
        const key = part.match(/^\{\{\s*([a-z_.]+)\s*\}\}$/)?.[1];
        if (key) {
          const token = byKey.get(key);
          if (!token) return <mark key={i} className="rounded bg-error-100 px-0.5 text-error-700">{part}</mark>;
          return (
            <mark key={i} title={token.label} className={cn("rounded px-0.5", token.kind === "case" ? "bg-[#fef0c7] text-[#93370d]" : "bg-[#ecfdf3] text-[#14532d]")}>
              {showExamples ? token.example : `«${token.label}»`}
            </mark>
          );
        }
        if (/^«[^«»\n]+»$/.test(part)) return <mark key={i} className="rounded bg-error-100 px-0.5 text-error-700">{part}</mark>;
        return <Fragment key={i}>{part}</Fragment>;
      })}
    </>
  );
}

/** "+ Párrafo / + Viñetas" between blocks, visible on hover. */
function InsertBar({ onAdd }: { onAdd: (kind: "text" | "list") => void }) {
  return (
    <div className="group/insert relative h-4">
      <div className="absolute inset-x-0 top-1/2 hidden -translate-y-1/2 items-center gap-2 group-hover/insert:flex" style={{ fontFamily: "var(--font-jakarta), sans-serif" }}>
        <span className="h-px flex-1 bg-brand-300" />
        <button type="button" onClick={() => onAdd("text")} className="rounded-full bg-brand-500 px-2.5 py-0.5 text-[11px] font-semibold text-white hover:bg-brand-600">
          + Párrafo
        </button>
        <button type="button" onClick={() => onAdd("list")} className="rounded-full bg-brand-500 px-2.5 py-0.5 text-[11px] font-semibold text-white hover:bg-brand-600">
          + Viñetas
        </button>
        <span className="h-px flex-1 bg-brand-300" />
      </div>
    </div>
  );
}

function Letterhead({ clinic }: { clinic: ClinicProfile | undefined }) {
  return (
    <header className="border-b-2 border-[#166534] pb-3">
      <div className="flex items-start justify-between gap-4">
        <div className="min-w-0">
          {clinic?.logo_url && <img src={`/api/backend/${clinic.logo_url}`} alt="" className="mb-1 h-10 w-auto" />}
          <p className="text-[20px] font-bold text-[#166534]">{clinic?.name ?? "Tu clínica"}</p>
          <p className="text-[10.5px] text-[#475467]">
            {[clinic?.legal_name, clinic?.tax_id && `NIT ${clinic.tax_id}`].filter(Boolean).join(" · ")}
            <br />
            {[clinic?.address, clinic?.phone].filter(Boolean).join(" · ")}
          </p>
        </div>
        <p className="shrink-0 text-right text-[10.5px] text-[#475467]">
          3 de octubre de 2026
          <br />
          Documento N.º ••••••
        </p>
      </div>
    </header>
  );
}

function Facts({ sample }: { sample: (key: string) => string }) {
  const facts: [string, string][] = [
    ["Paciente", sample("mascota")],
    ["Especie y raza", `${capitalize(sample("especie"))} · ${sample("raza")}`],
    ["Sexo · Edad · Peso", `${capitalize(sample("sexo"))} · ${sample("edad")} · ${sample("peso")}`],
    ["Microchip", "—"],
    ["Tutor", sample("tutor")],
    ["Documento", sample("tutor_doc")],
    ["Celular", sample("tutor_cel")],
    ["Médico veterinario", `${sample("veterinario")} · T.P. ${sample("veterinario_tp")}`],
  ];

  return (
    <dl className="mt-4 grid grid-cols-2 gap-x-4 gap-y-2 sm:grid-cols-4" style={{ pointerEvents: "none" }}>
      {facts.map(([term, value]) => (
        <div key={term} className="min-w-0">
          <dt className="text-[9px] tracking-wide text-[#475467] uppercase">{term}</dt>
          <dd className="text-[12px]">{value}</dd>
        </div>
      ))}
    </dl>
  );
}

function Signatures({ signers, sample }: { signers: TemplateType["signers"]; sample: (key: string) => string }) {
  const name = (role: string) => (role === "owner" ? sample("tutor") : role === "vet" ? sample("veterinario") : "Nombre del testigo");

  return (
    <div className={cn("mt-12 grid gap-6", signers.length === 3 ? "grid-cols-3" : "grid-cols-2")}>
      {signers.map((s) => (
        <div key={s.role}>
          <div className="h-12" />
          <div className="border-t border-[#101828] pt-1 text-[11px]">
            <p className="font-bold">{name(s.role)}</p>
            <p className="text-[#475467]">
              {s.label}
              {!s.required && " (si se pide)"}
            </p>
          </div>
        </div>
      ))}
    </div>
  );
}

function TokenGroup({ title, tokens, onPick, disabled }: { title: string; tokens: Token[]; onPick: (t: Token) => void; disabled: boolean }) {
  if (tokens.length === 0) return null;

  return (
    <div className="mt-3">
      <p className="mb-1.5 text-[11px] font-semibold tracking-wide text-gray-500 uppercase">{title}</p>
      <div className="flex flex-wrap gap-1.5">
        {tokens.map((t) => (
          <button
            key={t.key}
            type="button"
            disabled={disabled}
            onMouseDown={(e) => e.preventDefault() /* keep the cursor in the paragraph */}
            onClick={() => onPick(t)}
            title={`Ejemplo: ${t.example}`}
            className={cn(
              "rounded-md px-2 py-1 text-[11.5px] font-medium transition disabled:cursor-not-allowed disabled:opacity-50",
              t.kind === "case" ? "bg-warning-50 text-warning-800 hover:bg-warning-100 dark:bg-warning-500/10 dark:text-warning-300" : "bg-brand-50 text-brand-700 hover:bg-brand-100 dark:bg-brand-500/10 dark:text-brand-300",
            )}
          >
            {t.label}
          </button>
        ))}
      </div>
    </div>
  );
}

function fieldSample(f: DocumentField): string {
  if (f.type === "select") return Object.values(f.options ?? {})[0] ?? f.label;
  if (f.type === "money") return "$ 350.000";
  if (f.type === "number") return "3";
  if (f.type === "checkbox") return "sí";
  return f.placeholder?.replace(/^Ej\.: /, "") || f.label.toLowerCase();
}

const capitalize = (s: string) => s.charAt(0).toUpperCase() + s.slice(1);
