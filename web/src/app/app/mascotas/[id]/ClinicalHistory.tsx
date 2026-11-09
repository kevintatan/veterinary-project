"use client";

import { Card, EmptyState, Skeleton } from "@/components/common/Card";
import { useCan, useSession } from "@/components/providers/SessionProvider";
import PdfIcon from "@/components/common/PdfIcon";
import Button from "@/components/ui/button/Button";
import { addMonths, dayOf, formatMinutes, minutesOf } from "@/lib/agenda";
import { api, ApiError } from "@/lib/api";
import { todayIn } from "@/lib/dates";
import {
  ATTACHMENT_CATEGORIES,
  type ClinicalRecord,
  editWindowLeft,
  formatBytes,
  RECORD_TYPES,
  type RecordDetail,
  type RecordsPage,
  type RecordType,
  shortDate,
  typeMeta,
} from "@/lib/clinical";
import type { Pet } from "@/lib/patients";
import { cn } from "@/utils";
import { keepPreviousData, useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useEffect, useRef, useState } from "react";
import RecordFormPanel from "./RecordFormPanel";

/**
 * Clinical history as a timeline: newest first, one dot per record type,
 * voided records struck through (never deleted), locked ones with a padlock.
 */
export default function ClinicalHistory({ pet }: { pet: Pet }) {
  const canCreate = useCan("clinical_records.create");
  const [filter, setFilter] = useState<RecordType | "">("");
  const [page, setPage] = useState(1);
  const [open, setOpen] = useState<string | null>(null);
  const [form, setForm] = useState<{ type: RecordType; record?: ClinicalRecord } | null>(null);
  const [menu, setMenu] = useState(false);

  const records = useQuery({
    queryKey: ["clinical-records", pet.id, filter, page],
    queryFn: () => api<RecordsPage>(`pets/${pet.id}/clinical-records?page=${page}${filter ? `&type=${filter}` : ""}`),
    placeholderData: keepPreviousData,
  });

  const counts = records.data?.meta.counts;
  const total = counts ? Object.values(counts).reduce((sum, c) => sum + c.total, 0) : 0;
  const list = records.data?.data ?? [];
  const meta = records.data?.meta;

  return (
    <Card>
      <header className="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 px-5 py-4 dark:border-gray-800">
        <div>
          <h2 className="text-base font-semibold text-gray-800 dark:text-white/90">Historia clínica</h2>
          <p className="mt-0.5 text-theme-sm text-gray-500 dark:text-gray-400">{total === 0 ? "Sin registros todavía." : `${total} ${total === 1 ? "registro" : "registros"}`}</p>
        </div>
        <div className="flex items-center gap-2">
          {total > 0 && <ExportMenu petId={pet.id} filter={filter} />}
          {canCreate && !pet.deceased_at && <NewRecordMenu open={menu} setOpen={setMenu} onPick={(type) => setForm({ type })} />}
        </div>
      </header>

      {total > 0 && (
        <div className="flex gap-1.5 overflow-x-auto border-b border-gray-100 px-5 py-3 dark:border-gray-800" role="group" aria-label="Filtrar por tipo">
          <FilterChip active={filter === ""} onClick={() => (setFilter(""), setPage(1))}>
            Todo <span className="opacity-60">{total}</span>
          </FilterChip>
          {RECORD_TYPES.filter((t) => (counts?.[t.value]?.total ?? 0) > 0).map((t) => (
            <FilterChip key={t.value} active={filter === t.value} onClick={() => (setFilter(t.value), setPage(1))}>
              {t.short} <span className="opacity-60">{counts?.[t.value].total}</span>
            </FilterChip>
          ))}
        </div>
      )}

      <div className={cn("transition-opacity", records.isFetching && !records.isLoading && "opacity-60")}>
        {records.isLoading ? (
          <div className="space-y-3 p-5">
            {[1, 2, 3].map((i) => (
              <Skeleton key={i} className="h-16" />
            ))}
          </div>
        ) : records.isError ? (
          <EmptyState title="No fue posible cargar la historia clínica." />
        ) : list.length === 0 ? (
          <EmptyState title="Aún no hay registros clínicos." description={canCreate ? "Empieza con una consulta, una vacuna o una nota." : undefined} />
        ) : (
          <ol className="relative px-5 py-4">
            {/* Timeline rail */}
            <span className="absolute top-6 bottom-6 left-[2.15rem] w-px bg-gray-200 dark:bg-gray-800" aria-hidden />
            {list.map((record, i) => {
              const showDate = i === 0 || dayOf(list[i - 1].occurred_at) !== dayOf(record.occurred_at);
              return (
                <li key={record.id}>
                  {showDate && <p className="relative mb-2 ms-12 mt-3 text-theme-xs font-semibold tracking-wide text-gray-400 uppercase first:mt-0">{dateHeading(record.occurred_at)}</p>}
                  <TimelineItem
                    record={record}
                    open={open === record.id}
                    onToggle={() => setOpen((o) => (o === record.id ? null : record.id))}
                    onEdit={(full) => setForm({ type: record.type.value, record: full })}
                  />
                </li>
              );
            })}
          </ol>
        )}
      </div>

      {meta && meta.last_page > 1 && (
        <div className="flex items-center justify-between border-t border-gray-100 px-5 py-3 text-theme-sm text-gray-500 dark:border-gray-800">
          <span>
            Página {meta.current_page} de {meta.last_page}
          </span>
          <div className="flex gap-2">
            <Button size="sm" variant="outline" disabled={page <= 1} onClick={() => setPage((p) => p - 1)}>
              Más recientes
            </Button>
            <Button size="sm" variant="outline" disabled={page >= meta.last_page} onClick={() => setPage((p) => p + 1)}>
              Anteriores
            </Button>
          </div>
        </div>
      )}

      {form && <RecordFormPanel key={form.record?.id ?? form.type} pet={pet} type={form.type} record={form.record} onClose={() => setForm(null)} />}
    </Card>
  );
}

function dateHeading(iso: string): string {
  const date = new Date(`${dayOf(iso)}T00:00:00Z`);
  return new Intl.DateTimeFormat("es-CO", { weekday: "short", day: "numeric", month: "short", year: "numeric", timeZone: "UTC" }).format(date).replace(/\./g, "");
}

function FilterChip({ active, onClick, children }: { active: boolean; onClick: () => void; children: React.ReactNode }) {
  return (
    <button
      type="button"
      aria-pressed={active}
      onClick={onClick}
      className={cn(
        "shrink-0 rounded-full px-3 py-1.5 text-theme-xs font-medium whitespace-nowrap transition",
        active ? "bg-gray-900 text-white dark:bg-white dark:text-gray-900" : "bg-gray-100 text-gray-600 hover:bg-gray-200 dark:bg-white/5 dark:text-gray-300 dark:hover:bg-white/10",
      )}
    >
      {children}
    </button>
  );
}

function NewRecordMenu({ open, setOpen, onPick }: { open: boolean; setOpen: (v: boolean) => void; onPick: (type: RecordType) => void }) {
  const ref = useRef<HTMLDivElement>(null);

  useEffect(() => {
    if (!open) return;
    const close = (e: MouseEvent) => ref.current && !ref.current.contains(e.target as Node) && setOpen(false);
    const esc = (e: KeyboardEvent) => e.key === "Escape" && setOpen(false);
    document.addEventListener("mousedown", close);
    document.addEventListener("keydown", esc);
    return () => {
      document.removeEventListener("mousedown", close);
      document.removeEventListener("keydown", esc);
    };
  }, [open, setOpen]);

  return (
    <div ref={ref} className="relative">
      <Button size="sm" onClick={() => setOpen(!open)} aria-expanded={open}>
        Nuevo registro ▾
      </Button>
      {open && (
        <ul className="absolute right-0 z-30 mt-2 w-60 rounded-xl border border-gray-200 bg-white p-1.5 shadow-theme-lg dark:border-gray-800 dark:bg-gray-900" role="menu">
          {RECORD_TYPES.map((t) => (
            <li key={t.value} role="none">
              <button
                type="button"
                role="menuitem"
                onClick={() => {
                  setOpen(false);
                  onPick(t.value);
                }}
                className="flex w-full items-center gap-2.5 rounded-lg px-2.5 py-2 text-left text-theme-sm text-gray-700 hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-white/5"
              >
                <TypeDot type={t.value} />
                {t.label}
              </button>
            </li>
          ))}
        </ul>
      )}
    </div>
  );
}

const TYPE_INITIAL: Record<RecordType, string> = {
  consultation: "C",
  vaccination: "V",
  deworming: "D",
  prescription: "Rx",
  procedure: "P",
  follow_up: "S",
  note: "N",
};

function TypeDot({ type, className }: { type: RecordType; className?: string }) {
  return (
    <span className={cn("flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-[10px] font-bold ring-1", typeMeta(type).tone, className)} aria-hidden>
      {TYPE_INITIAL[type]}
    </span>
  );
}

function TimelineItem({ record, open, onToggle, onEdit }: { record: ClinicalRecord; open: boolean; onToggle: () => void; onEdit: (full: ClinicalRecord) => void }) {
  const left = record.is_voided ? null : editWindowLeft(record.locks_at);

  return (
    <div className="relative mb-2 flex gap-3">
      <TypeDot type={record.type.value} className="relative z-1 mt-2.5 ms-1 ring-4 ring-white dark:ring-gray-900" />
      <div className={cn("min-w-0 flex-1 rounded-xl border transition", open ? "border-gray-200 bg-white shadow-theme-xs dark:border-gray-700 dark:bg-white/3" : "border-transparent hover:bg-gray-50 dark:hover:bg-white/2")}>
        <button type="button" onClick={onToggle} aria-expanded={open} className="flex w-full items-start gap-3 px-3 py-2.5 text-left">
          <div className="min-w-0 flex-1">
            <p className="flex flex-wrap items-center gap-x-2 text-theme-xs text-gray-500 dark:text-gray-400">
              <span className="font-semibold text-gray-700 dark:text-gray-300">{record.type.label}</span>
              <span className="tabular">{formatMinutes(minutesOf(record.occurred_at))}</span>
              {record.author && <span>· {record.author.name}</span>}
            </p>
            <p className={cn("mt-0.5 text-theme-sm font-medium text-gray-900 dark:text-white", record.is_voided && "text-gray-400 line-through dark:text-gray-500")}>{record.summary}</p>
            <div className="mt-1 flex flex-wrap gap-1.5 text-[11px]">
              {record.is_voided && <span className="rounded-full bg-error-50 px-2 py-0.5 font-medium text-error-700 dark:bg-error-500/15 dark:text-error-400">Anulado</span>}
              {record.next_control_at && !record.is_voided && (
                <span className="rounded-full bg-brand-50 px-2 py-0.5 font-medium text-brand-700 dark:bg-brand-500/15 dark:text-brand-300">Próximo control {shortDate(record.next_control_at)}</span>
              )}
              {(record.attachments?.length ?? 0) > 0 && <span className="rounded-full bg-gray-100 px-2 py-0.5 text-gray-600 dark:bg-white/5 dark:text-gray-400">{record.attachments!.length} {record.attachments!.length === 1 ? "adjunto" : "adjuntos"}</span>}
            </div>
          </div>
          <span className="flex shrink-0 items-center gap-1 text-[11px] text-gray-400" title={record.is_locked ? "Cerrado: pasó la ventana de edición" : left ? `Editable ${left} más` : undefined}>
            {record.is_locked && !record.is_voided ? <LockIcon /> : left && <span className="tabular">editable {left}</span>}
          </span>
        </button>
        {open && <RecordBody id={record.id} onEdit={onEdit} />}
      </div>
    </div>
  );
}

function RecordBody({ id, onEdit }: { id: string; onEdit: (full: ClinicalRecord) => void }) {
  const queryClient = useQueryClient();
  const [voiding, setVoiding] = useState(false);
  const [reason, setReason] = useState("");

  const record = useQuery({
    queryKey: ["clinical-record", id],
    queryFn: () => api<{ data: ClinicalRecord }>(`clinical-records/${id}`).then((r) => r.data),
  });

  const voidRecord = useMutation({
    mutationFn: () => api<{ data: ClinicalRecord }>(`clinical-records/${id}/void`, { method: "POST", body: { reason } }),
    onSuccess: ({ data }) => {
      queryClient.setQueryData(["clinical-record", id], data);
      queryClient.invalidateQueries({ queryKey: ["clinical-records", data.pet_id] });
      setVoiding(false);
    },
  });

  if (record.isLoading) return <Skeleton className="mx-3 mb-3 h-24" />;
  if (!record.data) return <p className="px-3 pb-3 text-theme-sm text-error-600">No fue posible cargar el registro.</p>;

  const r = record.data;
  const voidError = voidRecord.error instanceof ApiError ? (voidRecord.error.field("reason") ?? voidRecord.error.message) : null;

  return (
    <div className="space-y-4 border-t border-gray-100 px-3 pt-3 pb-3 text-theme-sm dark:border-gray-800">
      {r.void && (
        <p className="rounded-lg bg-error-50 px-3 py-2 text-theme-xs text-error-800 dark:bg-error-500/10 dark:text-error-300">
          Anulado {r.void.by ? `por ${r.void.by} ` : ""}el {shortDate(r.void.at)}. Motivo: {r.void.reason}
        </p>
      )}

      <DetailView detail={r.detail ?? {}} />

      {r.next_control_at && (
        <p className="text-theme-xs text-gray-600 dark:text-gray-400">
          <span className="font-semibold">Próximo control:</span> {shortDate(r.next_control_at)}
          {r.next_control_note && ` · ${r.next_control_note}`}
        </p>
      )}

      <Attachments record={r} />

      {r.author && (
        <p className="text-theme-xs text-gray-500">
          Registrado por {r.author.name}
          {r.author.professional_license && ` · T.P. ${r.author.professional_license}`}
        </p>
      )}

      {!r.can_edit && !r.can_void && (
        <div className="border-t border-gray-100 pt-3 dark:border-gray-800">
          <PdfLink record={r} />
        </div>
      )}

      {(r.can_edit || r.can_void) &&
        (voiding ? (
          <form
            className="space-y-2 rounded-lg border border-error-200 p-3 dark:border-error-500/30"
            onSubmit={(e) => {
              e.preventDefault();
              voidRecord.mutate();
            }}
          >
            <label htmlFor={`void-${id}`} className="block text-theme-xs font-medium text-gray-700 dark:text-gray-300">
              Motivo de la anulación (queda visible en la historia)
            </label>
            <input
              id={`void-${id}`}
              autoFocus
              value={reason}
              onChange={(e) => setReason(e.target.value)}
              placeholder="Ej.: registrado en la mascota equivocada"
              className="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-theme-sm dark:border-gray-700 dark:text-white/90"
            />
            {voidError && <p className="text-theme-xs text-error-500">{voidError}</p>}
            <div className="flex justify-end gap-2">
              <Button type="button" size="sm" variant="outline" onClick={() => setVoiding(false)}>
                Volver
              </Button>
              <Button type="submit" size="sm" variant="danger" disabled={reason.trim().length < 5} loading={voidRecord.isPending}>
                Anular registro
              </Button>
            </div>
          </form>
        ) : (
          <div className="flex items-center gap-2 border-t border-gray-100 pt-3 dark:border-gray-800">
            <PdfLink record={r} />
            {r.can_edit && (
              <Button size="sm" variant="outline" onClick={() => onEdit(r)}>
                Editar
              </Button>
            )}
            {r.can_void && (
              <button type="button" onClick={() => setVoiding(true)} className="ms-auto text-theme-xs font-medium text-gray-500 hover:text-error-600">
                Anular
              </button>
            )}
          </div>
        ))}
    </div>
  );
}

/** Read-only rendering of a record's detail, whatever its type. */
function DetailView({ detail: d }: { detail: RecordDetail }) {
  const v = d.vital_signs;
  const vitals = v
    ? (
        [
          ["Peso", v.weight_kg, "kg"],
          ["Temp.", v.temperature_c, "°C"],
          ["FC", v.heart_rate, "lpm"],
          ["FR", v.respiratory_rate, "rpm"],
          ["TLLC", v.capillary_refill_s, "s"],
          ["CC", v.body_condition_score, "/9"],
          ["Dolor", v.pain_score, "/10"],
          ["SpO₂", v.spo2_pct, "%"],
        ] as const
      ).filter(([, value]) => value !== null)
    : [];

  return (
    <div className="space-y-4">
      {d.consultation && (
        <>
          <Block label="Motivo">{d.consultation.reason}</Block>
          <Block label="Anamnesis">{d.consultation.subjective}</Block>
        </>
      )}
      {d.follow_up && (
        <>
          <Block label={d.follow_up.kind.label}>{d.follow_up.reason}</Block>
          <Block label="Evolución">{d.follow_up.details}</Block>
        </>
      )}

      {(vitals.length > 0 || v?.mucous_membranes || v?.hydration) && (
        <div>
          <p className="mb-1.5 text-theme-xs font-semibold text-gray-500">Signos vitales</p>
          <div className="flex flex-wrap gap-1.5">
            {vitals.map(([label, value, unit]) => (
              <span key={label} className="rounded-lg bg-gray-50 px-2.5 py-1 text-theme-xs dark:bg-white/3">
                <span className="text-gray-500">{label}</span> <span className="font-semibold text-gray-800 tabular dark:text-white/90">{Number(value).toLocaleString("es-CO")}</span>
                <span className="text-gray-500">{unit.startsWith("/") ? unit : ` ${unit}`}</span>
              </span>
            ))}
            {v?.mucous_membranes && <span className="rounded-lg bg-gray-50 px-2.5 py-1 text-theme-xs dark:bg-white/3">Mucosas {v.mucous_membranes.label.toLowerCase()}</span>}
            {v?.hydration && <span className="rounded-lg bg-gray-50 px-2.5 py-1 text-theme-xs dark:bg-white/3">{v.hydration.label}</span>}
          </div>
        </div>
      )}

      {(d.findings?.length ?? 0) > 0 && (
        <div>
          <p className="mb-1.5 text-theme-xs font-semibold text-gray-500">Examen físico</p>
          <ul className="space-y-0.5 text-theme-sm">
            {d.findings!
              .filter((f) => f.status.value === "abnormal")
              .map((f) => (
                <li key={f.system.value}>
                  <span className="font-medium text-error-700 dark:text-error-400">{f.system.label}:</span> <span className="text-gray-700 dark:text-gray-300">{f.notes || "anormal"}</span>
                </li>
              ))}
            {d.findings!.some((f) => f.status.value === "normal") && (
              <li className="text-theme-xs text-gray-500">
                Normal: {d.findings!.filter((f) => f.status.value === "normal").map((f) => f.system.label.replace("Sistema ", "").toLowerCase()).join(", ")}
              </li>
            )}
          </ul>
        </div>
      )}
      {d.consultation && <Block label="Observaciones">{d.consultation.objective}</Block>}

      {(d.diagnoses?.length ?? 0) > 0 && (
        <div>
          <p className="mb-1.5 text-theme-xs font-semibold text-gray-500">Diagnóstico</p>
          <ul className="space-y-1">
            {d.diagnoses!.map((dx, i) => (
              <li key={i} className="flex items-baseline gap-2">
                <span className="w-24 shrink-0 text-theme-xs text-gray-500">{dx.kind.label}</span>
                <span className="font-medium text-gray-800 dark:text-white/90">{dx.description}</span>
              </li>
            ))}
          </ul>
        </div>
      )}
      {d.consultation && (
        <>
          <Block label="Análisis">{d.consultation.assessment}</Block>
          <Block label="Plan terapéutico">{d.consultation.plan_treatment}</Block>
          <Block label="Plan diagnóstico">{d.consultation.plan_diagnostic}</Block>
        </>
      )}

      {d.vaccination && (
        <dl className="grid grid-cols-2 gap-x-4 gap-y-2 sm:grid-cols-3">
          <Pair term="Vacuna">{d.vaccination.vaccine_name}</Pair>
          <Pair term="Laboratorio">{d.vaccination.manufacturer}</Pair>
          <Pair term="Lote">{[d.vaccination.lot_number, d.vaccination.lot_expires_on && `vence ${d.vaccination.lot_expires_on}`].filter(Boolean).join(" · ")}</Pair>
          <Pair term="Dosis">{d.vaccination.dose_number}</Pair>
          <Pair term="Vía">{d.vaccination.route}</Pair>
        </dl>
      )}

      {d.deworming && (
        <dl className="grid grid-cols-3 gap-4">
          <Pair term="Tipo">{d.deworming.kind.label}</Pair>
          <Pair term="Producto">{d.deworming.product_name}</Pair>
          <Pair term="Dosis">{d.deworming.dose}</Pair>
        </dl>
      )}

      {d.prescription && (
        <div className="space-y-3">
          <Block label="Diagnóstico">{d.prescription.diagnosis}</Block>
          <ol className="space-y-2">
            {d.prescription.items.map((item, i) => (
              <li key={i} className="rounded-lg bg-gray-50 px-3 py-2 dark:bg-white/3">
                <p className="font-semibold text-gray-900 dark:text-white">
                  {i + 1}. {item.medication} {item.presentation && <span className="font-normal text-gray-500">{item.presentation}</span>}
                </p>
                <p className="text-theme-sm text-gray-700 dark:text-gray-300">{[item.dose, item.route, item.frequency, item.duration].filter(Boolean).join(" · ")}</p>
                {(item.quantity || item.notes) && <p className="text-theme-xs text-gray-500">{[item.quantity && `Cantidad: ${item.quantity}`, item.notes].filter(Boolean).join(" · ")}</p>}
              </li>
            ))}
          </ol>
          <Block label="Indicaciones">{d.prescription.instructions}</Block>
        </div>
      )}

      {d.procedure && (
        <>
          <Block label="Procedimiento">{d.procedure.name}</Block>
          <Block label="Descripción">{d.procedure.description}</Block>
          <Block label="Premedicación">{d.procedure.premedication}</Block>
          <Block label="Anestesia">{d.procedure.anesthesia}</Block>
          <Block label="Complicaciones">{d.procedure.complications}</Block>
          <Block label="Resultado">{d.procedure.outcome}</Block>
        </>
      )}

      {d.note && <p className="whitespace-pre-line text-gray-800 dark:text-gray-200">{d.note.body}</p>}
    </div>
  );
}

function Block({ label, children }: { label: string; children: React.ReactNode }) {
  if (!children) return null;
  return (
    <div>
      <p className="mb-0.5 text-theme-xs font-semibold text-gray-500">{label}</p>
      <p className="whitespace-pre-line text-gray-800 dark:text-gray-200">{children}</p>
    </div>
  );
}

function Pair({ term, children }: { term: string; children: React.ReactNode }) {
  return (
    <div>
      <dt className="text-theme-xs text-gray-500">{term}</dt>
      <dd className="text-gray-800 dark:text-white/90">{children || "—"}</dd>
    </div>
  );
}

function Attachments({ record }: { record: ClinicalRecord }) {
  const queryClient = useQueryClient();
  const input = useRef<HTMLInputElement>(null);
  const [category, setCategory] = useState("lab_result");
  const files = record.attachments ?? [];
  const refresh = () => {
    queryClient.invalidateQueries({ queryKey: ["clinical-record", record.id] });
    queryClient.invalidateQueries({ queryKey: ["clinical-records", record.pet_id] });
  };

  const upload = useMutation({
    mutationFn: (file: File) => {
      const body = new FormData();
      body.append("file", file);
      body.append("category", category);
      return api(`clinical-records/${record.id}/attachments`, { method: "POST", body });
    },
    onSuccess: refresh,
  });
  const remove = useMutation({ mutationFn: (attachmentId: string) => api(`attachments/${attachmentId}`, { method: "DELETE" }), onSuccess: refresh });

  const error = upload.error instanceof ApiError ? (upload.error.field("file") ?? upload.error.message) : null;
  if (files.length === 0 && (!record.can_edit || record.is_voided)) return null;

  return (
    <div>
      <p className="mb-1.5 text-theme-xs font-semibold text-gray-500">Adjuntos</p>
      {files.length > 0 && (
        <ul className="mb-2 space-y-1">
          {files.map((f) => (
            <li key={f.id} className="flex items-center gap-2 rounded-lg border border-gray-100 px-3 py-1.5 dark:border-gray-800">
              <a href={`/api/backend/attachments/${f.id}/download`} className="min-w-0 flex-1 truncate text-theme-sm font-medium text-brand-700 hover:underline dark:text-brand-400">
                {f.original_name}
              </a>
              <span className="shrink-0 text-theme-xs text-gray-400">
                {ATTACHMENT_CATEGORIES.find(([k]) => k === f.category)?.[1] ?? f.category} · {formatBytes(f.size_bytes)}
              </span>
              {record.can_edit && (
                <button type="button" onClick={() => remove.mutate(f.id)} aria-label={`Quitar ${f.original_name}`} className="shrink-0 text-gray-400 hover:text-error-600">
                  ✕
                </button>
              )}
            </li>
          ))}
        </ul>
      )}
      {record.can_edit && !record.is_voided && (
        <div className="flex flex-wrap items-center gap-2">
          <select aria-label="Tipo de adjunto" value={category} onChange={(e) => setCategory(e.target.value)} className="h-8 rounded-lg border border-gray-200 bg-transparent px-2 text-theme-xs text-gray-700 dark:border-gray-700 dark:text-gray-300">
            {ATTACHMENT_CATEGORIES.map(([k, l]) => (
              <option key={k} value={k}>
                {l}
              </option>
            ))}
          </select>
          <button type="button" onClick={() => input.current?.click()} disabled={upload.isPending} className="h-8 rounded-lg border border-dashed border-gray-300 px-3 text-theme-xs font-medium text-gray-600 hover:border-brand-400 hover:text-brand-700 dark:border-gray-700 dark:text-gray-400">
            {upload.isPending ? "Subiendo…" : "+ Adjuntar archivo"}
          </button>
          <span className="text-[11px] text-gray-400">PDF, imágenes, Word, Excel · máx. 20 MB</span>
          <input
            ref={input}
            type="file"
            className="hidden"
            accept=".jpg,.jpeg,.png,.webp,.heic,.pdf,.doc,.docx,.xls,.xlsx,.txt,.dcm"
            onChange={(e) => {
              const file = e.target.files?.[0];
              if (file) upload.mutate(file);
              e.target.value = "";
            }}
          />
        </div>
      )}
      {error && <p className="mt-1 text-theme-xs text-error-500">{error}</p>}
    </div>
  );
}

/** Opens the record's PDF in a new tab (the browser's viewer prints or shares it). */
function PdfLink({ record }: { record: ClinicalRecord }) {
  return (
    <a
      href={`/api/backend/clinical-records/${record.id}/pdf`}
      target="_blank"
      rel="noopener"
      className="inline-flex h-9 items-center gap-2 rounded-lg px-3 text-theme-sm font-medium text-gray-700 ring-1 ring-gray-300 ring-inset hover:bg-gray-50 dark:text-gray-300 dark:ring-gray-700 dark:hover:bg-white/5"
    >
      <PdfIcon />
      {record.type.value === "prescription" ? "Imprimir fórmula" : "Descargar PDF"}
    </a>
  );
}

const PERIODS = [
  ["all", "Toda la historia"],
  ["12m", "Últimos 12 meses"],
  ["custom", "Elegir fechas"],
] as const;

/** Export the history: whole or a period, the current type filter, with or without voided records. */
function ExportMenu({ petId, filter }: { petId: string; filter: RecordType | "" }) {
  const { clinic } = useSession();
  const [open, setOpen] = useState(false);
  const [period, setPeriod] = useState<(typeof PERIODS)[number][0]>("all");
  const [range, setRange] = useState({ from: "", to: "" });
  const [withVoided, setWithVoided] = useState(true);
  const ref = useRef<HTMLDivElement>(null);

  useEffect(() => {
    if (!open) return;
    const close = (e: MouseEvent) => ref.current && !ref.current.contains(e.target as Node) && setOpen(false);
    const esc = (e: KeyboardEvent) => e.key === "Escape" && setOpen(false);
    document.addEventListener("mousedown", close);
    document.addEventListener("keydown", esc);
    return () => {
      document.removeEventListener("mousedown", close);
      document.removeEventListener("keydown", esc);
    };
  }, [open]);

  const url = () => {
    const params = new URLSearchParams();
    const today = todayIn(clinic.timezone);
    if (period === "12m") params.set("from", addMonths(today, -12)); // clamps 29 feb
    if (period === "custom") {
      if (range.from) params.set("from", range.from);
      if (range.to) params.set("to", range.to);
    }
    if (filter) params.append("types[]", filter);
    if (!withVoided) params.set("include_voided", "0");
    return `/api/backend/pets/${petId}/clinical-history/pdf${params.size ? `?${params}` : ""}`;
  };

  return (
    <div ref={ref} className="relative">
      <Button size="sm" variant="outline" startIcon={<PdfIcon />} onClick={() => setOpen((v) => !v)} aria-expanded={open}>
        Descargar PDF
      </Button>
      {open && (
        <div className="absolute right-0 z-30 mt-2 w-72 space-y-3 rounded-xl border border-gray-200 bg-white p-4 shadow-theme-lg dark:border-gray-800 dark:bg-gray-900">
          <p className="text-theme-sm font-semibold text-gray-800 dark:text-white/90">Historia clínica en PDF</p>
          <div className="space-y-1.5" role="radiogroup" aria-label="Periodo">
            {PERIODS.map(([value, label]) => (
              <label key={value} className="flex cursor-pointer items-center gap-2 text-theme-sm text-gray-700 dark:text-gray-300">
                <input type="radio" name="pdf-period" checked={period === value} onChange={() => setPeriod(value)} className="accent-brand-500" />
                {label}
              </label>
            ))}
          </div>
          {period === "custom" && (
            <div className="grid grid-cols-2 gap-2">
              <input type="date" aria-label="Desde" value={range.from} onChange={(e) => setRange((r) => ({ ...r, from: e.target.value }))} className="h-9 rounded-lg border border-gray-300 bg-transparent px-2 text-theme-xs dark:border-gray-700 dark:text-white/90" />
              <input type="date" aria-label="Hasta" value={range.to} onChange={(e) => setRange((r) => ({ ...r, to: e.target.value }))} className="h-9 rounded-lg border border-gray-300 bg-transparent px-2 text-theme-xs dark:border-gray-700 dark:text-white/90" />
            </div>
          )}
          {filter && <p className="rounded-lg bg-gray-50 px-2.5 py-1.5 text-theme-xs text-gray-600 dark:bg-white/3 dark:text-gray-400">Solo: {typeMeta(filter).short.toLowerCase()} (filtro actual)</p>}
          <label className="flex cursor-pointer items-center gap-2 text-theme-xs text-gray-600 dark:text-gray-400">
            <input type="checkbox" checked={withVoided} onChange={(e) => setWithVoided(e.target.checked)} className="accent-brand-500" />
            Incluir registros anulados (tachados)
          </label>
          <a
            href={url()}
            target="_blank"
            rel="noopener"
            onClick={() => setOpen(false)}
            className="flex h-9 items-center justify-center gap-2 rounded-lg bg-brand-500 text-theme-sm font-medium text-white hover:bg-brand-600"
          >
            <PdfIcon className="text-white" />
            Generar PDF
          </a>
          <p className="text-[11px] text-gray-400">La descarga queda registrada en la auditoría.</p>
        </div>
      )}
    </div>
  );
}

function LockIcon() {
  return (
    <svg width="13" height="13" viewBox="0 0 20 20" fill="none" aria-label="Cerrado">
      <rect x="4" y="9" width="12" height="8" rx="2" stroke="currentColor" strokeWidth="1.6" />
      <path d="M7 9V6.5a3 3 0 0 1 6 0V9" stroke="currentColor" strokeWidth="1.6" />
    </svg>
  );
}
