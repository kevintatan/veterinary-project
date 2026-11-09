import type { Labelled, PetAlert } from "./types";

/** Option lists mirror the API enums (api/app/Enums/PetAttributes.php, DocumentType.php). */
export const DOCUMENT_TYPES: [string, string][] = [
  ["CC", "Cédula de ciudadanía"],
  ["CE", "Cédula de extranjería"],
  ["PPT", "Permiso por protección temporal"],
  ["PAS", "Pasaporte"],
  ["TI", "Tarjeta de identidad"],
  ["NIT", "NIT"],
  ["NUIP", "Registro civil (NUIP)"],
  ["DIE", "Documento extranjero"],
];

export const SEX: [string, string][] = [
  ["female", "Hembra"],
  ["male", "Macho"],
  ["unknown", "No definido"],
];

export const SIZE: [string, string][] = [
  ["toy", "Miniatura"],
  ["small", "Pequeño"],
  ["medium", "Mediano"],
  ["large", "Grande"],
  ["giant", "Gigante"],
];

export const REPRODUCTIVE_STATUS: [string, string][] = [
  ["intact", "No esterilizado"],
  ["neutered", "Esterilizado"],
  ["unknown", "Desconocido"],
];

export const TEMPERAMENT: [string, string][] = [
  ["very_docile", "Muy dócil"],
  ["docile", "Dócil"],
  ["reserved", "Reservado"],
  ["reactive", "Reactivo"],
  ["aggressive", "Agresivo"],
];

export const ALERT_TYPES: [PetAlert["type"]["value"], string][] = [
  ["allergy", "Alergia"],
  ["behavior", "Comportamiento"],
  ["chronic_condition", "Condición crónica"],
  ["infectious_disease", "Enfermedad infecciosa"],
  ["other", "Otra"],
];

export const ALERT_SEVERITY: [PetAlert["severity"]["value"], string][] = [
  ["high", "Alta"],
  ["medium", "Media"],
  ["low", "Baja"],
];

export const REFERRAL_SOURCES: [string, string][] = [
  ["referral", "Recomendación"],
  ["walk_in", "Pasaba por aquí"],
  ["instagram", "Instagram"],
  ["facebook", "Facebook"],
  ["google", "Google / Maps"],
  ["other", "Otro"],
];

export type Consents = { data_processing: boolean; whatsapp: boolean; sms: boolean; email: boolean; marketing: boolean };

export type PetListItem = {
  id: string;
  name: string;
  species: { id: string; name: string; code: string } | null;
  breed: { id: string; name: string } | null;
  breed_other: string | null;
  sex: Labelled | null;
  age: string | null;
  weight_kg: number | null;
  photo_url: string | null;
  deceased_at: string | null;
  alerts?: PetAlert[];
};

export type Owner = {
  id: string;
  document_type: Labelled | null;
  document_number: string | null;
  full_name: string;
  email: string | null;
  mobile_phone: string | null;
  phone: string | null;
  address: string | null;
  municipality_code: string | null;
  neighborhood: string | null;
  alt_contact_name: string | null;
  alt_contact_phone: string | null;
  referral_source: string | null;
  important_notes: string | null;
  consents?: Consents;
  pets: PetListItem[];
  created_at: string;
  updated_at: string;
};

export type Pet = PetListItem & {
  owner_id: string;
  birth_date: string | null;
  birth_date_is_estimated: boolean;
  color: string | null;
  size: Labelled | null;
  reproductive_status: Labelled | null;
  temperament: Labelled | null;
  diet: string | null;
  microchip: string | null;
  is_service_animal: boolean;
  is_emotional_support: boolean;
  deceased_reason: string | null;
  important_notes: string | null;
  alerts: PetAlert[];
  owner: { id: string; full_name: string; mobile_phone: string | null };
};

/**
 * Colombian-friendly phone input → E.164 the API expects.
 * "312 333 4455" → "+573123334455"; "+1 305…" is kept as international.
 */
export function toE164(input: string): string | null {
  const trimmed = input.trim();
  if (trimmed === "") return null;
  const digits = trimmed.replace(/\D/g, "");
  if (trimmed.startsWith("+")) return `+${digits}`;
  if (digits.length === 10 && (digits.startsWith("3") || digits.startsWith("60"))) return `+57${digits}`;
  if (digits.length === 12 && digits.startsWith("57")) return `+${digits}`;
  return `+${digits}`;
}

/** "+573123334455" → "312 333 4455" (Colombian numbers lose the +57 for editing/reading). */
export function fromE164(phone: string | null): string {
  if (!phone) return "";
  const m = phone.match(/^\+57(\d{3})(\d{3})(\d{4})$/);
  return m ? `${m[1]} ${m[2]} ${m[3]}` : phone;
}

/** Display form: "+57 312 333 4455". */
export function formatPhone(phone: string | null): string {
  if (!phone) return "";
  const m = phone.match(/^\+57(\d{3})(\d{3})(\d{4})$/);
  return m ? `+57 ${m[1]} ${m[2]} ${m[3]}` : phone;
}

export function whatsappLink(phone: string): string {
  return `https://wa.me/${phone.replace(/\D/g, "")}`;
}

export function initials(name: string): string {
  return name
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((w) => w[0]?.toUpperCase())
    .join("");
}

export function petSummary(pet: Pick<PetListItem, "species" | "breed" | "breed_other" | "sex" | "age" | "weight_kg">): string {
  return [pet.species?.name, pet.breed?.name ?? pet.breed_other, pet.sex?.label, pet.age, pet.weight_kg && `${pet.weight_kg.toLocaleString("es-CO")} kg`]
    .filter(Boolean)
    .join(" · ");
}
