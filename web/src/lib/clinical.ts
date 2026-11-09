import type { Labelled } from "./types";

/** Mirrors api/app/Clinical/Vocabulary.php and ClinicalRecord::TYPES. */
export type RecordType = "consultation" | "vaccination" | "deworming" | "prescription" | "procedure" | "follow_up" | "note";

/** Fixed color + short glyph per record type (timeline dot and filter chips). */
export const RECORD_TYPES: { value: RecordType; label: string; short: string; tone: string }[] = [
  { value: "consultation", label: "Consulta", short: "Consultas", tone: "bg-brand-50 text-brand-700 ring-brand-200 dark:bg-brand-500/15 dark:text-brand-300 dark:ring-brand-500/30" },
  { value: "vaccination", label: "Vacunación", short: "Vacunas", tone: "bg-blue-light-50 text-blue-light-700 ring-blue-light-200 dark:bg-blue-light-500/15 dark:text-blue-light-300 dark:ring-blue-light-500/30" },
  { value: "deworming", label: "Desparasitación", short: "Desparasitaciones", tone: "bg-success-50 text-success-700 ring-success-200 dark:bg-success-500/15 dark:text-success-300 dark:ring-success-500/30" },
  { value: "prescription", label: "Fórmula médica", short: "Fórmulas", tone: "bg-warning-50 text-warning-700 ring-warning-200 dark:bg-warning-500/15 dark:text-warning-300 dark:ring-warning-500/30" },
  { value: "procedure", label: "Cirugía / procedimiento", short: "Procedimientos", tone: "bg-accent-50 text-accent-700 ring-accent-200 dark:bg-accent-500/15 dark:text-accent-300 dark:ring-accent-500/30" },
  { value: "follow_up", label: "Seguimiento", short: "Seguimientos", tone: "bg-gray-100 text-gray-700 ring-gray-200 dark:bg-white/5 dark:text-gray-300 dark:ring-gray-700" },
  { value: "note", label: "Nota clínica", short: "Notas", tone: "bg-gray-100 text-gray-700 ring-gray-200 dark:bg-white/5 dark:text-gray-300 dark:ring-gray-700" },
];

export const typeMeta = (type: RecordType) => RECORD_TYPES.find((t) => t.value === type)!;

export const EXAM_SYSTEMS: [string, string][] = [
  ["attitude", "Actitud"],
  ["hydration", "Hidratación"],
  ["nutrition", "Estado nutricional"],
  ["lymph_nodes", "Ganglios linfáticos"],
  ["mucous_membranes", "Mucosas"],
  ["cardiovascular", "Cardiovascular"],
  ["respiratory", "Respiratorio"],
  ["digestive", "Digestivo"],
  ["urinary", "Urinario"],
  ["reproductive", "Reproductivo"],
  ["musculoskeletal", "Musculoesquelético"],
  ["nervous", "Nervioso"],
  ["skin", "Piel y anexos"],
  ["eyes", "Ojos"],
  ["ears", "Oídos"],
];

export const DIAGNOSIS_KIND: [string, string][] = [
  ["presumptive", "Presuntivo"],
  ["differential", "Diferencial"],
  ["definitive", "Definitivo"],
];

export const MUCOUS_MEMBRANES: [string, string][] = [
  ["pink", "Rosadas"],
  ["pale", "Pálidas"],
  ["cyanotic", "Cianóticas"],
  ["icteric", "Ictéricas"],
  ["hyperemic", "Hiperémicas"],
];

export const HYDRATION: [string, string][] = [
  ["normal", "Normal"],
  ["mild", "Deshidratación leve"],
  ["moderate", "Deshidratación moderada"],
  ["severe", "Deshidratación severa"],
];

export const DEWORMING_KIND: [string, string][] = [
  ["internal", "Interna"],
  ["external", "Externa"],
  ["both", "Interna y externa"],
];

export const FOLLOW_UP_KIND: [string, string][] = [
  ["phone_call", "Llamada"],
  ["in_person", "Presencial"],
  ["post_op", "Postoperatorio"],
  ["other", "Otro"],
];

export const ATTACHMENT_CATEGORIES: [string, string][] = [
  ["lab_result", "Laboratorio"],
  ["imaging", "Imagenología"],
  ["image", "Foto"],
  ["document", "Documento"],
  ["other", "Otro"],
];

export type VitalSigns = {
  weight_kg: number | null;
  temperature_c: number | null;
  heart_rate: number | null;
  respiratory_rate: number | null;
  capillary_refill_s: number | null;
  spo2_pct: number | null;
  systolic_bp: number | null;
  diastolic_bp: number | null;
  glucose_mg_dl: number | null;
  body_condition_score: number | null;
  mucous_membranes: Labelled | null;
  hydration: Labelled | null;
  pain_score: number | null;
  notes: string | null;
};

export type PrescriptionItem = {
  medication: string;
  presentation: string | null;
  dose: string;
  frequency: string;
  duration: string | null;
  route: string | null;
  quantity: string | null;
  notes: string | null;
};

export type RecordDetail = {
  consultation?: {
    reason: string;
    subjective: string | null;
    objective: string | null;
    assessment: string | null;
    plan_treatment: string | null;
    plan_diagnostic: string | null;
  } | null;
  vital_signs?: VitalSigns | null;
  findings?: { system: Labelled; status: Labelled; notes: string | null }[];
  diagnoses?: { kind: Labelled; description: string; code: string | null }[];
  vaccination?: {
    vaccine_id: string | null;
    vaccine_name: string;
    manufacturer: string | null;
    lot_number: string | null;
    lot_expires_on: string | null;
    dose_number: number | null;
    route: string | null;
  } | null;
  deworming?: { kind: Labelled; product_name: string; dose: string | null } | null;
  prescription?: { diagnosis: string | null; instructions: string | null; items: PrescriptionItem[] } | null;
  procedure?: {
    procedure_type_id: string | null;
    name: string;
    description: string | null;
    premedication: string | null;
    anesthesia: string | null;
    complications: string | null;
    outcome: string | null;
  } | null;
  follow_up?: { kind: Labelled; reason: string; details: string | null } | null;
  note?: { body: string } | null;
};

export type Attachment = {
  id: string;
  original_name: string;
  mime_type: string;
  size_bytes: number;
  category: string;
  visible_to_owner: boolean;
};

export type ClinicalRecord = {
  id: string;
  type: Labelled<RecordType>;
  summary: string;
  occurred_at: string;
  pet_id: string;
  appointment_id: string | null;
  author: { id: string; name: string; professional_license: string | null } | null;
  next_control_at: string | null;
  next_control_note: string | null;
  visible_to_owner: boolean;
  is_locked: boolean;
  locks_at: string | null;
  is_voided: boolean;
  void: { at: string; reason: string; by: string | null } | null;
  can_edit: boolean;
  can_void: boolean;
  detail?: RecordDetail;
  attachments?: Attachment[];
  created_at: string;
};

export type RecordsPage = {
  data: ClinicalRecord[];
  meta: { current_page: number; last_page: number; total: number; counts: Record<RecordType, { label: string; total: number }> };
};

export function formatBytes(bytes: number): string {
  if (bytes < 1024) return `${bytes} B`;
  if (bytes < 1024 * 1024) return `${Math.round(bytes / 1024)} KB`;
  return `${(bytes / 1024 / 1024).toLocaleString("es-CO", { maximumFractionDigits: 1 })} MB`;
}

/** "editable 18 h más" / "editable 25 min más" from locks_at. */
export function editWindowLeft(locksAt: string | null): string | null {
  if (!locksAt) return null;
  const minutes = Math.round((new Date(locksAt).getTime() - Date.now()) / 60_000);
  if (minutes <= 0) return null;
  return minutes >= 60 ? `${Math.floor(minutes / 60)} h` : `${minutes} min`;
}

/** "2027-10-01" → "1 oct 2027". */
export function shortDate(day: string): string {
  return new Intl.DateTimeFormat("es-CO", { day: "numeric", month: "short", year: "numeric", timeZone: "UTC" })
    .format(new Date(`${day.slice(0, 10)}T00:00:00Z`))
    .replace(/\./g, "")
    .replace(/ de /g, " ");
}
