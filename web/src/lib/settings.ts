import type { Labelled } from "./types";

export type ClinicProfile = {
  id: string;
  name: string;
  legal_name: string | null;
  tax_id: string | null;
  email: string | null;
  phone: string | null;
  address: string | null;
  website: string | null;
  logo_url: string | null;
  timezone: string;
  settings: { edit_window_hours: number; prevent_overlaps: boolean; default_appointment_minutes: number };
};

export type Branch = {
  id: string;
  name: string;
  address: string | null;
  municipality_code: string | null;
  phone: string | null;
  is_active: boolean;
};

export type Member = {
  id: string;
  user: { id: string; name: string; email: string; phone: string | null; professional_license: string | null };
  role: { name: RoleName; label: string };
  status: "active" | "disabled";
  shows_in_agenda: boolean;
  agenda_color: string | null;
  branches: { id: string; name: string }[];
  joined_at: string;
};

export type Invitation = {
  id: string;
  email: string;
  role: { name: RoleName; label: string };
  branch_ids: string[] | null;
  invited_by: string | null;
  expires_at: string;
  created_at: string;
};

export type RoleName = "admin" | "veterinarian" | "receptionist";

export const ROLES: { value: RoleName; label: string; description: string }[] = [
  { value: "admin", label: "Administrador", description: "Todo, incluida la configuración, el equipo y la facturación." },
  { value: "veterinarian", label: "Veterinario", description: "Agenda, pacientes e historia clínica." },
  { value: "receptionist", label: "Recepción", description: "Agenda, tutores y mascotas. No escribe historia clínica." },
];

export const SERVICE_CATEGORIES: [string, string][] = [
  ["consultation", "Consulta"],
  ["vaccination", "Vacunación"],
  ["deworming", "Desparasitación"],
  ["grooming", "Peluquería y spa"],
  ["surgery", "Cirugía"],
  ["lab", "Laboratorio"],
  ["imaging", "Imágenes diagnósticas"],
  ["hospitalization", "Hospitalización"],
  ["other", "Otro"],
];

export type ServiceItem = {
  id: string;
  name: string;
  category: Labelled;
  duration_minutes: number;
  price: number;
  tax_rate: number;
  is_bookable_online: boolean;
  color: string | null;
  is_active: boolean;
};

/** Palette offered for agenda colors (distinct, readable on white and dark). */
export const AGENDA_COLORS = ["#166534", "#2563EB", "#9333EA", "#DB2777", "#EA580C", "#CA8A04", "#0891B2", "#475467"];

export const WEEKDAYS: [number, string][] = [
  [1, "Lunes"],
  [2, "Martes"],
  [3, "Miércoles"],
  [4, "Jueves"],
  [5, "Viernes"],
  [6, "Sábado"],
  [7, "Domingo"],
];

export function formatCop(value: number): string {
  return new Intl.NumberFormat("es-CO", { style: "currency", currency: "COP", maximumFractionDigits: 0 }).format(value);
}
