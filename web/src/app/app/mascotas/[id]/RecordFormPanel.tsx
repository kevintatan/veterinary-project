"use client";

import SidePanel from "@/components/common/SidePanel";
import { Field, FormSection, Input, inputClass, Select, Textarea } from "@/components/form/fields";
import { useSession } from "@/components/providers/SessionProvider";
import Button from "@/components/ui/button/Button";
import { api, ApiError } from "@/lib/api";
import {
  type ClinicalRecord,
  DEWORMING_KIND,
  DIAGNOSIS_KIND,
  EXAM_SYSTEMS,
  FOLLOW_UP_KIND,
  HYDRATION,
  MUCOUS_MEMBRANES,
  type PrescriptionItem,
  type RecordType,
  typeMeta,
  type VitalSigns,
} from "@/lib/clinical";
import type { Pet } from "@/lib/patients";
import { cn } from "@/utils";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useState } from "react";
import ProductUseField, { type ProductUse } from "./ProductUseField";

type Vitals = Record<Exclude<keyof VitalSigns, "mucous_membranes" | "hydration">, string> & { mucous_membranes: string; hydration: string };
type Finding = { status: "" | "normal" | "abnormal"; notes: string };
type Diagnosis = { kind: string; description: string };
type Item = Record<keyof PrescriptionItem, string>;

const EMPTY_ITEM: Item = { medication: "", presentation: "", dose: "", frequency: "", duration: "", route: "", quantity: "", notes: "" };

const VITAL_FIELDS: { key: keyof Vitals; label: string; unit: string; step?: string }[] = [
  { key: "weight_kg", label: "Peso", unit: "kg", step: "0.01" },
  { key: "temperature_c", label: "Temperatura", unit: "°C", step: "0.1" },
  { key: "heart_rate", label: "Frec. cardiaca", unit: "lpm" },
  { key: "respiratory_rate", label: "Frec. respiratoria", unit: "rpm" },
  { key: "capillary_refill_s", label: "Llenado capilar", unit: "s", step: "0.5" },
  { key: "body_condition_score", label: "Condición corporal", unit: "/9" },
  { key: "pain_score", label: "Dolor", unit: "/10" },
  { key: "spo2_pct", label: "SpO₂", unit: "%" },
];

/** Datetime-local value "now" in the clinic timezone (the API reads offset-less times as clinic time). */
function nowLocal(timeZone: string): string {
  const parts = Object.fromEntries(
    new Intl.DateTimeFormat("en-CA", { timeZone, year: "numeric", month: "2-digit", day: "2-digit", hour: "2-digit", minute: "2-digit", hourCycle: "h23" })
      .formatToParts(new Date())
      .map((p) => [p.type, p.value]),
  );
  return `${parts.year}-${parts.month}-${parts.day}T${parts.hour}:${parts.minute}`;
}

const str = (v: unknown) => (v === null || v === undefined ? "" : String(v));
const orNull = (v: string) => (v.trim() === "" ? null : v.trim());
const numOrNull = (v: string) => (v.trim() === "" ? null : Number(v.replace(",", ".")));

/**
 * Create or edit a clinical record. One panel for every type: the common part
 * (date, next control) plus the sections of that type.
 */
export default function RecordFormPanel({
  pet,
  type,
  record,
  onClose,
}: {
  pet: Pet;
  type: RecordType;
  record?: ClinicalRecord; // with detail, when editing
  onClose: () => void;
}) {
  const { clinic, branches } = useSession();
  const queryClient = useQueryClient();
  const editing = !!record;
  const d = record?.detail ?? {};
  const meta = typeMeta(type);

  const [branchId, setBranchId] = useState(branches[0]?.id ?? "");
  const [occurredAt, setOccurredAt] = useState(record ? record.occurred_at.slice(0, 16) : nowLocal(clinic.timezone));
  const [nextControl, setNextControl] = useState({ at: str(record?.next_control_at), note: str(record?.next_control_note) });

  const [vitals, setVitals] = useState<Vitals>(() => {
    const v = d.vital_signs;
    return {
      weight_kg: str(v?.weight_kg ?? (editing ? "" : pet.weight_kg)),
      temperature_c: str(v?.temperature_c),
      heart_rate: str(v?.heart_rate),
      respiratory_rate: str(v?.respiratory_rate),
      capillary_refill_s: str(v?.capillary_refill_s),
      spo2_pct: str(v?.spo2_pct),
      systolic_bp: str(v?.systolic_bp),
      diastolic_bp: str(v?.diastolic_bp),
      glucose_mg_dl: str(v?.glucose_mg_dl),
      body_condition_score: str(v?.body_condition_score),
      pain_score: str(v?.pain_score),
      notes: str(v?.notes),
      mucous_membranes: v?.mucous_membranes?.value ?? "",
      hydration: v?.hydration?.value ?? "",
    };
  });

  const [consultation, setConsultation] = useState({
    reason: str(d.consultation?.reason),
    subjective: str(d.consultation?.subjective),
    objective: str(d.consultation?.objective),
    assessment: str(d.consultation?.assessment),
    plan_treatment: str(d.consultation?.plan_treatment),
    plan_diagnostic: str(d.consultation?.plan_diagnostic),
  });
  const [findings, setFindings] = useState<Record<string, Finding>>(() =>
    Object.fromEntries(
      EXAM_SYSTEMS.map(([key]) => {
        const f = d.findings?.find((x) => x.system.value === key);
        return [key, { status: (f && f.status.value !== "not_evaluated" ? f.status.value : "") as Finding["status"], notes: str(f?.notes) }];
      }),
    ),
  );
  const [diagnoses, setDiagnoses] = useState<Diagnosis[]>(d.diagnoses?.map((x) => ({ kind: x.kind.value, description: x.description })) ?? []);

  const [vaccination, setVaccination] = useState({
    vaccine_id: str(d.vaccination?.vaccine_id),
    vaccine_name: str(d.vaccination?.vaccine_name),
    manufacturer: str(d.vaccination?.manufacturer),
    lot_number: str(d.vaccination?.lot_number),
    lot_expires_on: str(d.vaccination?.lot_expires_on),
    dose_number: str(d.vaccination?.dose_number),
    route: str(d.vaccination?.route) || "SC",
  });
  const [deworming, setDeworming] = useState({ kind: d.deworming?.kind.value ?? "internal", product_name: str(d.deworming?.product_name), dose: str(d.deworming?.dose) });
  const [prescription, setPrescription] = useState({
    diagnosis: str(d.prescription?.diagnosis),
    instructions: str(d.prescription?.instructions),
    items: (d.prescription?.items.map((i) => Object.fromEntries(Object.entries(i).map(([k, v]) => [k, str(v)])) as Item) ?? [{ ...EMPTY_ITEM }]) as Item[],
  });
  const [procedure, setProcedure] = useState({
    procedure_type_id: str(d.procedure?.procedure_type_id),
    name: str(d.procedure?.name),
    description: str(d.procedure?.description),
    premedication: str(d.procedure?.premedication),
    anesthesia: str(d.procedure?.anesthesia),
    complications: str(d.procedure?.complications),
    outcome: str(d.procedure?.outcome),
  });
  const [followUp, setFollowUp] = useState({ kind: d.follow_up?.kind.value ?? "phone_call", reason: str(d.follow_up?.reason), details: str(d.follow_up?.details) });
  const [note, setNote] = useState(str(d.note?.body));
  // Fase 5a · V3: inventory product used (only when creating a vaccine, deworming or procedure).
  const [productUse, setProductUse] = useState<ProductUse | null>(null);
  const productUseBody =
    !editing && productUse && ["vaccination", "deworming", "procedure"].includes(type)
      ? { product_use: { product_id: productUse.product.id, quantity: Number(productUse.quantity || 1), charge: productUse.charge } }
      : {};

  const vaccines = useQuery({
    queryKey: ["catalogs", "vaccines", pet.species?.id],
    queryFn: () =>
      api<{ data: { id: string; name: string; manufacturer: string | null; default_booster_days: number | null; is_active: boolean }[] }>(
        `catalogs/vaccines${pet.species ? `?species_id=${pet.species.id}` : ""}`,
      ).then((r) => r.data.filter((v) => v.is_active)),
    enabled: type === "vaccination",
    staleTime: 10 * 60_000,
  });
  const procedures = useQuery({
    queryKey: ["catalogs", "procedure-types"],
    queryFn: () => api<{ data: { id: string; name: string; is_active: boolean }[] }>("catalogs/procedure-types").then((r) => r.data.filter((p) => p.is_active)),
    enabled: type === "procedure",
    staleTime: 10 * 60_000,
  });

  const vitalsPayload = () => {
    const entries = Object.entries(vitals).map(([k, v]) => [k, ["mucous_membranes", "hydration", "notes"].includes(k) ? orNull(v) : numOrNull(v)]);
    return entries.every(([, v]) => v === null) ? null : Object.fromEntries(entries);
  };

  const buildBody = () => {
    const common = {
      ...(editing ? {} : { type }),
      occurred_at: occurredAt,
      ...(editing ? {} : { branch_id: branchId || null }),
      // Left out when empty on creation, so the API can suggest it (e.g. the vaccine's booster date).
      ...(editing || nextControl.at ? { next_control_at: orNull(nextControl.at) } : {}),
      next_control_note: orNull(nextControl.note),
      ...productUseBody,
    };

    switch (type) {
      case "consultation":
        return {
          ...common,
          consultation: Object.fromEntries(Object.entries(consultation).map(([k, v]) => [k, k === "reason" ? v.trim() : orNull(v)])),
          vital_signs: vitalsPayload(),
          findings: Object.entries(findings)
            .filter(([, f]) => f.status !== "")
            .map(([system, f]) => ({ system, status: f.status, notes: orNull(f.notes) })),
          diagnoses: diagnoses.filter((x) => x.description.trim()).map((x) => ({ kind: x.kind, description: x.description.trim() })),
        };
      case "vaccination":
        return {
          ...common,
          vaccination: {
            vaccine_id: orNull(vaccination.vaccine_id),
            vaccine_name: vaccination.vaccine_id ? null : orNull(vaccination.vaccine_name),
            manufacturer: orNull(vaccination.manufacturer),
            lot_number: orNull(vaccination.lot_number),
            lot_expires_on: orNull(vaccination.lot_expires_on),
            dose_number: vaccination.dose_number ? Number(vaccination.dose_number) : null,
            route: orNull(vaccination.route),
          },
        };
      case "deworming":
        return { ...common, deworming: { kind: deworming.kind, product_name: deworming.product_name.trim(), dose: orNull(deworming.dose) } };
      case "prescription":
        return {
          ...common,
          prescription: {
            diagnosis: orNull(prescription.diagnosis),
            instructions: orNull(prescription.instructions),
            items: prescription.items
              .filter((i) => i.medication.trim())
              .map((i) => Object.fromEntries(Object.entries(i).map(([k, v]) => [k, ["medication", "dose", "frequency"].includes(k) ? v.trim() : orNull(v)]))),
          },
        };
      case "procedure":
        return {
          ...common,
          procedure: {
            ...Object.fromEntries(Object.entries(procedure).map(([k, v]) => [k, orNull(v)])),
            name: procedure.procedure_type_id ? null : orNull(procedure.name),
          },
        };
      case "follow_up":
        return { ...common, follow_up: { kind: followUp.kind, reason: followUp.reason.trim(), details: orNull(followUp.details) }, vital_signs: vitalsPayload() };
      case "note":
        return { ...common, note: { body: note.trim() } };
    }
  };

  const save = useMutation({
    mutationFn: () =>
      editing
        ? api<{ data: ClinicalRecord }>(`clinical-records/${record.id}`, { method: "PATCH", body: buildBody() })
        : api<{ data: ClinicalRecord }>(`pets/${pet.id}/clinical-records`, { method: "POST", body: buildBody() }),
    onSuccess: ({ data }) => {
      queryClient.invalidateQueries({ queryKey: ["clinical-records", pet.id] });
      queryClient.setQueryData(["clinical-record", data.id], data);
      queryClient.invalidateQueries({ queryKey: ["pets", pet.id] }); // weight may have changed
      if (productUse) {
        queryClient.invalidateQueries({ queryKey: ["inventory"] });
        queryClient.invalidateQueries({ queryKey: ["sales"] });
      }
      onClose();
    },
  });

  const error = save.error instanceof ApiError ? save.error : null;
  const fe = (name: string) => error?.field(name);
  const firstError = error ? (Object.values(error.errors)[0]?.[0] ?? error.message) : null;

  const valid = (() => {
    switch (type) {
      case "consultation":
        return consultation.reason.trim() !== "";
      case "vaccination":
        return !!vaccination.vaccine_id || vaccination.vaccine_name.trim() !== "";
      case "deworming":
        return deworming.product_name.trim() !== "";
      case "prescription":
        return prescription.items.some((i) => i.medication.trim() && i.dose.trim() && i.frequency.trim());
      case "procedure":
        return !!procedure.procedure_type_id || procedure.name.trim() !== "";
      case "follow_up":
        return followUp.reason.trim() !== "";
      case "note":
        return note.trim() !== "";
    }
  })();

  const vitalsSection = (
    <FormSection title="Signos vitales">
      <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
        {VITAL_FIELDS.map((f) => (
          <div key={f.key}>
            <label htmlFor={`v-${f.key}`} className="mb-1 block text-theme-xs font-medium text-gray-600 dark:text-gray-400">
              {f.label}
            </label>
            <div className="relative">
              <input
                id={`v-${f.key}`}
                type="number"
                inputMode="decimal"
                step={f.step ?? "1"}
                value={vitals[f.key]}
                onChange={(e) => setVitals((v) => ({ ...v, [f.key]: e.target.value }))}
                aria-invalid={!!fe(`vital_signs.${f.key}`) || undefined}
                className={cn(inputClass, "h-10 pe-10 tabular", fe(`vital_signs.${f.key}`) && "border-error-300")}
              />
              <span className="pointer-events-none absolute top-1/2 right-3 -translate-y-1/2 text-theme-xs text-gray-400">{f.unit}</span>
            </div>
          </div>
        ))}
      </div>
      <div className="grid gap-3 sm:grid-cols-2">
        <Field label="Mucosas" htmlFor="v-mm">
          <Select id="v-mm" value={vitals.mucous_membranes} onChange={(e) => setVitals((v) => ({ ...v, mucous_membranes: e.target.value }))}>
            <option value="">—</option>
            {MUCOUS_MEMBRANES.map(([k, l]) => (
              <option key={k} value={k}>
                {l}
              </option>
            ))}
          </Select>
        </Field>
        <Field label="Hidratación" htmlFor="v-hyd">
          <Select id="v-hyd" value={vitals.hydration} onChange={(e) => setVitals((v) => ({ ...v, hydration: e.target.value }))}>
            <option value="">—</option>
            {HYDRATION.map(([k, l]) => (
              <option key={k} value={k}>
                {l}
              </option>
            ))}
          </Select>
        </Field>
      </div>
    </FormSection>
  );

  return (
    <SidePanel
      width="max-w-3xl"
      title={
        <span className="flex items-center gap-2">
          <span className={cn("rounded-md px-2 py-0.5 text-theme-xs font-semibold ring-1", meta.tone)}>{meta.label}</span>
          {editing ? "Editar registro" : `Nuevo registro · ${pet.name}`}
        </span>
      }
      description={editing ? `${pet.name} · ${record.summary}` : [pet.species?.name, pet.breed?.name, pet.age].filter(Boolean).join(" · ")}
      onClose={onClose}
      footer={
        <div className="flex items-center justify-between gap-3">
          <p className="min-w-0 truncate text-theme-xs text-error-500">{firstError}</p>
          <div className="flex shrink-0 gap-3">
            <Button variant="outline" size="sm" onClick={onClose}>
              Cancelar
            </Button>
            <Button size="sm" type="submit" form="record-form" disabled={!valid || save.isPending} loading={save.isPending}>
              {editing ? "Guardar cambios" : "Guardar registro"}
            </Button>
          </div>
        </div>
      }
    >
      <form
        id="record-form"
        className="space-y-6"
        onSubmit={(e) => {
          e.preventDefault();
          if (valid) save.mutate();
        }}
      >
        {pet.alerts.some((a) => a.is_active) && (
          <ul className="space-y-1">
            {pet.alerts
              .filter((a) => a.is_active)
              .map((a) => (
                <li
                  key={a.id}
                  className={cn(
                    "rounded-lg px-3 py-1.5 text-theme-xs",
                    a.type.value === "behavior" ? "bg-warning-50 text-warning-800 dark:bg-warning-500/10 dark:text-warning-300" : "bg-error-50 text-error-800 dark:bg-error-500/10 dark:text-error-300",
                  )}
                >
                  <span className="font-semibold">{a.type.label}:</span> {a.description}
                </li>
              ))}
          </ul>
        )}

        {/* ---- Per type ---- */}
        {type === "consultation" && (
          <>
            <FormSection title="Motivo">
              <Field label="Motivo de consulta" htmlFor="c-reason" error={fe("consultation.reason")}>
                <Input id="c-reason" autoFocus value={consultation.reason} onChange={(e) => setConsultation((c) => ({ ...c, reason: e.target.value }))} placeholder="Ej.: vómito desde ayer, decaído" maxLength={255} />
              </Field>
              <Field label="Anamnesis (subjetivo)" htmlFor="c-subj" hint="Lo que cuenta el tutor: inicio, evolución, apetito, deposiciones…">
                <Textarea id="c-subj" rows={3} value={consultation.subjective} onChange={(e) => setConsultation((c) => ({ ...c, subjective: e.target.value }))} />
              </Field>
            </FormSection>

            {vitalsSection}

            <FormSection title="Examen físico">
              <p className="-mt-2 text-theme-xs text-gray-500">Marca solo lo que evaluaste. Lo anormal pide una nota.</p>
              <div className="grid gap-x-4 rounded-xl border border-gray-200 px-1 sm:grid-cols-2 dark:border-gray-800">
                {EXAM_SYSTEMS.map(([key, label]) => {
                  const f = findings[key];
                  const setF = (patch: Partial<Finding>) => setFindings((all) => ({ ...all, [key]: { ...all[key], ...patch } }));
                  return (
                    <div key={key} className="border-b border-gray-100 px-2 py-2 dark:border-gray-800">
                      <div className="flex items-center justify-between gap-3">
                        <span className="text-theme-sm text-gray-700 dark:text-gray-300">{label}</span>
                        <div className="inline-flex rounded-lg bg-gray-100 p-0.5 text-theme-xs dark:bg-white/5" role="radiogroup" aria-label={label}>
                          {(
                            [
                              ["", "—"],
                              ["normal", "Normal"],
                              ["abnormal", "Anormal"],
                            ] as const
                          ).map(([value, text]) => (
                            <button
                              key={value}
                              type="button"
                              role="radio"
                              aria-checked={f.status === value}
                              onClick={() => setF({ status: value })}
                              className={cn(
                                "rounded-md px-2.5 py-1 font-medium",
                                f.status === value
                                  ? value === "abnormal"
                                    ? "bg-error-500 text-white"
                                    : value === "normal"
                                      ? "bg-white text-success-700 shadow-theme-xs dark:bg-gray-800 dark:text-success-400"
                                      : "bg-white text-gray-700 shadow-theme-xs dark:bg-gray-800 dark:text-gray-300"
                                  : "text-gray-500",
                              )}
                            >
                              {text}
                            </button>
                          ))}
                        </div>
                      </div>
                      {f.status === "abnormal" && (
                        <Input className="mt-2 h-9" aria-label={`Hallazgo en ${label}`} placeholder="¿Qué encontraste?" value={f.notes} onChange={(e) => setF({ notes: e.target.value })} maxLength={1000} autoFocus />
                      )}
                    </div>
                  );
                })}
              </div>
              <div className="flex gap-2">
                <button
                  type="button"
                  onClick={() => setFindings((all) => Object.fromEntries(Object.entries(all).map(([k, f]) => [k, f.status === "" ? { ...f, status: "normal" } : f])))}
                  className="text-theme-xs font-medium text-brand-600 hover:underline dark:text-brand-400"
                >
                  Marcar el resto como normal
                </button>
              </div>
              <Field label="Observaciones del examen (objetivo)" htmlFor="c-obj">
                <Textarea id="c-obj" rows={2} value={consultation.objective} onChange={(e) => setConsultation((c) => ({ ...c, objective: e.target.value }))} />
              </Field>
            </FormSection>

            <FormSection title="Diagnóstico">
              {diagnoses.map((dx, i) => (
                <div key={i} className="flex gap-2">
                  <Select aria-label="Tipo de diagnóstico" className="w-40 shrink-0" value={dx.kind} onChange={(e) => setDiagnoses((all) => all.map((x, j) => (j === i ? { ...x, kind: e.target.value } : x)))}>
                    {DIAGNOSIS_KIND.map(([k, l]) => (
                      <option key={k} value={k}>
                        {l}
                      </option>
                    ))}
                  </Select>
                  <Input aria-label="Diagnóstico" value={dx.description} onChange={(e) => setDiagnoses((all) => all.map((x, j) => (j === i ? { ...x, description: e.target.value } : x)))} placeholder="Ej.: Gastroenteritis aguda" maxLength={255} autoFocus={i === diagnoses.length - 1} />
                  <button type="button" aria-label="Quitar diagnóstico" onClick={() => setDiagnoses((all) => all.filter((_, j) => j !== i))} className="shrink-0 rounded-lg px-2 text-gray-400 hover:bg-gray-100 hover:text-error-600 dark:hover:bg-white/5">
                    ✕
                  </button>
                </div>
              ))}
              <button type="button" onClick={() => setDiagnoses((all) => [...all, { kind: all.length === 0 ? "presumptive" : "differential", description: "" }])} className="text-theme-sm font-medium text-brand-600 hover:underline dark:text-brand-400">
                + Agregar diagnóstico
              </button>
              <Field label="Análisis" htmlFor="c-assess" hint="Razonamiento clínico, pronóstico.">
                <Textarea id="c-assess" rows={2} value={consultation.assessment} onChange={(e) => setConsultation((c) => ({ ...c, assessment: e.target.value }))} />
              </Field>
            </FormSection>

            <FormSection title="Plan">
              <div className="grid gap-3 sm:grid-cols-2">
                <Field label="Plan terapéutico" htmlFor="c-ptx">
                  <Textarea id="c-ptx" rows={3} value={consultation.plan_treatment} onChange={(e) => setConsultation((c) => ({ ...c, plan_treatment: e.target.value }))} placeholder="Tratamiento aplicado e indicado" />
                </Field>
                <Field label="Plan diagnóstico" htmlFor="c-pdx">
                  <Textarea id="c-pdx" rows={3} value={consultation.plan_diagnostic} onChange={(e) => setConsultation((c) => ({ ...c, plan_diagnostic: e.target.value }))} placeholder="Exámenes solicitados" />
                </Field>
              </div>
              <p className="text-theme-xs text-gray-500">¿Recetaste medicamentos? Guarda la consulta y agrega una “Fórmula médica”: queda lista para imprimir.</p>
            </FormSection>
          </>
        )}

        {type === "vaccination" && (
          <FormSection title="Vacuna aplicada">
            <Field label="Vacuna" htmlFor="vx-id" hint="Al elegir del catálogo se calcula el próximo refuerzo.">
              <Select
                id="vx-id"
                autoFocus
                value={vaccination.vaccine_id}
                onChange={(e) => {
                  const v = vaccines.data?.find((x) => x.id === e.target.value);
                  setVaccination((x) => ({ ...x, vaccine_id: e.target.value, manufacturer: x.manufacturer || v?.manufacturer || "" }));
                }}
              >
                <option value="">{vaccines.isLoading ? "Cargando…" : "Otra (escribir)"}</option>
                {vaccines.data?.map((v) => (
                  <option key={v.id} value={v.id}>
                    {v.name}
                    {v.default_booster_days ? ` · refuerzo ${v.default_booster_days >= 365 ? `${Math.round(v.default_booster_days / 365)} año` : `${v.default_booster_days} días`}` : ""}
                  </option>
                ))}
              </Select>
            </Field>
            {!vaccination.vaccine_id && (
              <Field label="Nombre de la vacuna" htmlFor="vx-name" error={fe("vaccination.vaccine_name")}>
                <Input id="vx-name" value={vaccination.vaccine_name} onChange={(e) => setVaccination((x) => ({ ...x, vaccine_name: e.target.value }))} maxLength={120} />
              </Field>
            )}
            <div className="grid gap-3 sm:grid-cols-3">
              <Field label="Laboratorio" htmlFor="vx-man" optional>
                <Input id="vx-man" value={vaccination.manufacturer} onChange={(e) => setVaccination((x) => ({ ...x, manufacturer: e.target.value }))} maxLength={80} />
              </Field>
              <Field label="Lote" htmlFor="vx-lot" optional>
                <Input id="vx-lot" value={vaccination.lot_number} onChange={(e) => setVaccination((x) => ({ ...x, lot_number: e.target.value }))} maxLength={60} />
              </Field>
              <Field label="Vence el lote" htmlFor="vx-exp" optional>
                <Input id="vx-exp" type="date" value={vaccination.lot_expires_on} onChange={(e) => setVaccination((x) => ({ ...x, lot_expires_on: e.target.value }))} />
              </Field>
            </div>
            <div className="grid gap-3 sm:grid-cols-3">
              <Field label="Dosis n.º" htmlFor="vx-dose" optional>
                <Input id="vx-dose" type="number" min={1} max={20} value={vaccination.dose_number} onChange={(e) => setVaccination((x) => ({ ...x, dose_number: e.target.value }))} />
              </Field>
              <Field label="Vía" htmlFor="vx-route">
                <Select id="vx-route" value={vaccination.route} onChange={(e) => setVaccination((x) => ({ ...x, route: e.target.value }))}>
                  <option value="SC">Subcutánea</option>
                  <option value="IM">Intramuscular</option>
                  <option value="IN">Intranasal</option>
                  <option value="VO">Oral</option>
                </Select>
              </Field>
            </div>
            {!editing && (
              <ProductUseField
                branchId={branchId}
                kind="vaccine"
                value={productUse}
                onChange={setProductUse}
                onPicked={(product, lot) =>
                  setVaccination((x) => ({
                    ...x,
                    vaccine_name: x.vaccine_id || x.vaccine_name ? x.vaccine_name : product.name,
                    lot_number: x.lot_number || lot?.number || "",
                    lot_expires_on: x.lot_expires_on || lot?.expires_on || "",
                  }))
                }
              />
            )}
          </FormSection>
        )}

        {type === "deworming" && (
          <FormSection title="Desparasitación">
            <div className="grid gap-3 sm:grid-cols-[1fr_2fr]">
              <Field label="Tipo" htmlFor="dw-kind">
                <Select id="dw-kind" value={deworming.kind} onChange={(e) => setDeworming((x) => ({ ...x, kind: e.target.value }))}>
                  {DEWORMING_KIND.map(([k, l]) => (
                    <option key={k} value={k}>
                      {l}
                    </option>
                  ))}
                </Select>
              </Field>
              <Field label="Producto" htmlFor="dw-prod" error={fe("deworming.product_name")}>
                <Input id="dw-prod" autoFocus value={deworming.product_name} onChange={(e) => setDeworming((x) => ({ ...x, product_name: e.target.value }))} placeholder="Ej.: Bravecto 10–20 kg" maxLength={120} />
              </Field>
            </div>
            <Field label="Dosis" htmlFor="dw-dose" optional>
              <Input id="dw-dose" value={deworming.dose} onChange={(e) => setDeworming((x) => ({ ...x, dose: e.target.value }))} placeholder="Ej.: 1 tableta" maxLength={60} />
            </Field>
            {!editing && (
              <ProductUseField
                branchId={branchId}
                kind="medication"
                value={productUse}
                onChange={setProductUse}
                onPicked={(product) => setDeworming((x) => ({ ...x, product_name: x.product_name || product.name }))}
              />
            )}
          </FormSection>
        )}

        {type === "prescription" && (
          <FormSection title="Fórmula médica">
            <Field label="Diagnóstico" htmlFor="rx-dx" optional>
              <Input id="rx-dx" value={prescription.diagnosis} onChange={(e) => setPrescription((p) => ({ ...p, diagnosis: e.target.value }))} maxLength={255} />
            </Field>
            {prescription.items.map((item, i) => {
              const setItem = (patch: Partial<Item>) => setPrescription((p) => ({ ...p, items: p.items.map((x, j) => (j === i ? { ...x, ...patch } : x)) }));
              return (
                <div key={i} className="space-y-3 rounded-xl border border-gray-200 p-4 dark:border-gray-800">
                  <div className="flex items-center justify-between">
                    <span className="text-theme-xs font-semibold text-gray-500">Medicamento {i + 1}</span>
                    {prescription.items.length > 1 && (
                      <button type="button" onClick={() => setPrescription((p) => ({ ...p, items: p.items.filter((_, j) => j !== i) }))} className="text-theme-xs text-gray-400 hover:text-error-600">
                        Quitar
                      </button>
                    )}
                  </div>
                  <div className="grid gap-3 sm:grid-cols-[2fr_1fr]">
                    <Input aria-label="Medicamento" autoFocus={i === 0} placeholder="Medicamento (ej.: Amoxicilina + ácido clavulánico)" value={item.medication} onChange={(e) => setItem({ medication: e.target.value })} maxLength={150} />
                    <Input aria-label="Presentación" placeholder="Presentación (250 mg)" value={item.presentation} onChange={(e) => setItem({ presentation: e.target.value })} maxLength={100} />
                  </div>
                  <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                    <Input aria-label="Dosis" placeholder="Dosis (1 tab)" value={item.dose} onChange={(e) => setItem({ dose: e.target.value })} maxLength={100} />
                    <Input aria-label="Frecuencia" placeholder="Cada 12 h" value={item.frequency} onChange={(e) => setItem({ frequency: e.target.value })} maxLength={100} />
                    <Input aria-label="Duración" placeholder="Por 7 días" value={item.duration} onChange={(e) => setItem({ duration: e.target.value })} maxLength={100} />
                    <Input aria-label="Vía" placeholder="Vía oral" value={item.route} onChange={(e) => setItem({ route: e.target.value })} maxLength={50} />
                  </div>
                  <div className="grid gap-3 sm:grid-cols-[1fr_2fr]">
                    <Input aria-label="Cantidad" placeholder="Cantidad (14 tab)" value={item.quantity} onChange={(e) => setItem({ quantity: e.target.value })} maxLength={50} />
                    <Input aria-label="Indicaciones" placeholder="Indicaciones (con comida…)" value={item.notes} onChange={(e) => setItem({ notes: e.target.value })} maxLength={500} />
                  </div>
                </div>
              );
            })}
            <button type="button" onClick={() => setPrescription((p) => ({ ...p, items: [...p.items, { ...EMPTY_ITEM }] }))} className="text-theme-sm font-medium text-brand-600 hover:underline dark:text-brand-400">
              + Agregar medicamento
            </button>
            <Field label="Indicaciones generales" htmlFor="rx-ins" optional>
              <Textarea id="rx-ins" rows={2} value={prescription.instructions} onChange={(e) => setPrescription((p) => ({ ...p, instructions: e.target.value }))} placeholder="Ej.: dieta blanda 3 días, volver si no mejora" />
            </Field>
          </FormSection>
        )}

        {type === "procedure" && (
          <FormSection title="Procedimiento">
            <Field label="Procedimiento" htmlFor="pr-type">
              <Select id="pr-type" autoFocus value={procedure.procedure_type_id} onChange={(e) => setProcedure((p) => ({ ...p, procedure_type_id: e.target.value }))}>
                <option value="">{procedures.isLoading ? "Cargando…" : "Otro (escribir)"}</option>
                {procedures.data?.map((p) => (
                  <option key={p.id} value={p.id}>
                    {p.name}
                  </option>
                ))}
              </Select>
            </Field>
            {!procedure.procedure_type_id && (
              <Field label="Nombre del procedimiento" htmlFor="pr-name" error={fe("procedure.name")}>
                <Input id="pr-name" value={procedure.name} onChange={(e) => setProcedure((p) => ({ ...p, name: e.target.value }))} maxLength={150} />
              </Field>
            )}
            <Field label="Descripción" htmlFor="pr-desc">
              <Textarea id="pr-desc" rows={3} value={procedure.description} onChange={(e) => setProcedure((p) => ({ ...p, description: e.target.value }))} />
            </Field>
            <div className="grid gap-3 sm:grid-cols-2">
              <Field label="Premedicación" htmlFor="pr-pre" optional>
                <Textarea id="pr-pre" rows={2} value={procedure.premedication} onChange={(e) => setProcedure((p) => ({ ...p, premedication: e.target.value }))} />
              </Field>
              <Field label="Anestesia" htmlFor="pr-an" optional>
                <Textarea id="pr-an" rows={2} value={procedure.anesthesia} onChange={(e) => setProcedure((p) => ({ ...p, anesthesia: e.target.value }))} />
              </Field>
              <Field label="Complicaciones" htmlFor="pr-comp" optional>
                <Textarea id="pr-comp" rows={2} value={procedure.complications} onChange={(e) => setProcedure((p) => ({ ...p, complications: e.target.value }))} />
              </Field>
              <Field label="Resultado" htmlFor="pr-out" optional>
                <Textarea id="pr-out" rows={2} value={procedure.outcome} onChange={(e) => setProcedure((p) => ({ ...p, outcome: e.target.value }))} />
              </Field>
            </div>
            {!editing && <ProductUseField branchId={branchId} value={productUse} onChange={setProductUse} />}
          </FormSection>
        )}

        {type === "follow_up" && (
          <>
            <FormSection title="Seguimiento">
              <div className="grid gap-3 sm:grid-cols-[1fr_2fr]">
                <Field label="Tipo" htmlFor="fu-kind">
                  <Select id="fu-kind" value={followUp.kind} onChange={(e) => setFollowUp((f) => ({ ...f, kind: e.target.value }))}>
                    {FOLLOW_UP_KIND.map(([k, l]) => (
                      <option key={k} value={k}>
                        {l}
                      </option>
                    ))}
                  </Select>
                </Field>
                <Field label="Motivo" htmlFor="fu-reason" error={fe("follow_up.reason")}>
                  <Input id="fu-reason" autoFocus value={followUp.reason} onChange={(e) => setFollowUp((f) => ({ ...f, reason: e.target.value }))} placeholder="Ej.: control postoperatorio día 3" maxLength={255} />
                </Field>
              </div>
              <Field label="Evolución" htmlFor="fu-det">
                <Textarea id="fu-det" rows={4} value={followUp.details} onChange={(e) => setFollowUp((f) => ({ ...f, details: e.target.value }))} />
              </Field>
            </FormSection>
            {followUp.kind !== "phone_call" && vitalsSection}
          </>
        )}

        {type === "note" && (
          <FormSection title="Nota">
            <Textarea aria-label="Nota clínica" autoFocus rows={8} value={note} onChange={(e) => setNote(e.target.value)} />
          </FormSection>
        )}

        {/* ---- Common ---- */}
        <FormSection title="Fecha y próximo control">
          {!editing && branches.length > 1 && (
            <Field label="Sede" htmlFor="r-branch" error={fe("branch_id")}>
              <Select id="r-branch" value={branchId} onChange={(e) => setBranchId(e.target.value)}>
                {branches.map((b) => (
                  <option key={b.id} value={b.id}>
                    {b.name}
                  </option>
                ))}
              </Select>
            </Field>
          )}
          <div className="grid gap-3 sm:grid-cols-3">
            <Field label="Fecha de atención" htmlFor="r-at" error={fe("occurred_at")}>
              <Input id="r-at" type="datetime-local" value={occurredAt} onChange={(e) => setOccurredAt(e.target.value)} required />
            </Field>
            <Field label="Próximo control" htmlFor="r-next" optional hint={type === "vaccination" && vaccination.vaccine_id ? "Vacío = según el refuerzo de la vacuna." : undefined}>
              <Input id="r-next" type="date" value={nextControl.at} onChange={(e) => setNextControl((n) => ({ ...n, at: e.target.value }))} />
            </Field>
            <Field label="Nota del control" htmlFor="r-next-note" optional>
              <Input id="r-next-note" value={nextControl.note} onChange={(e) => setNextControl((n) => ({ ...n, note: e.target.value }))} maxLength={255} />
            </Field>
          </div>
          {!editing && <p className="text-theme-xs text-gray-500">Podrás corregir este registro durante 24 horas; después queda cerrado y solo se puede anular con motivo.</p>}
        </FormSection>
      </form>
    </SidePanel>
  );
}
