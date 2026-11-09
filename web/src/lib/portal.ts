import type { CardInfo } from "@/components/pet-card/types";

/** Owner portal (Fase 4e): shapes returned by /api/v1/portal/*. */
export type PortalAppointment = {
  id: string;
  starts_at: string;
  is_time_unset: boolean;
  status: { value: string; label: string };
  service: string | null;
  branch: { name: string; address: string | null } | null;
};

export type PortalPetSummary = {
  id: string;
  name: string;
  species: string | null;
  species_code: string | null;
  breed: string | null;
  age: string | null;
  deceased: boolean;
  photo_url: string | null;
  clinic: { id: string; name: string };
  next_appointment: PortalAppointment | null;
  vaccines_expired: number;
};

export type PortalPet = CardInfo & { deceased: boolean; upcoming: PortalAppointment[] };

export type PortalMe = { id: string; name: string; email: string; phone: string };

/** Clinics of the account that take online requests (docs/reservas.md, B3). */
export type PortalBookingClinic = {
  name: string;
  slug: string;
  prefill: { name: string; phone: string | null; document_type: string | null; document_number: string | null };
  pets: { id: string; name: string; species_id: string }[];
};

/** BFF the portal's images go through (see app/api/tutor). */
export const TUTOR_BASE = "/api/tutor";

/** "martes 6 de oct, 9:30 a. m." in the clinic's time (the API already sends local time). */
export function appointmentWhen(a: PortalAppointment): string {
  const at = shift(a.starts_at);
  const day = new Intl.DateTimeFormat("es-CO", { weekday: "long", day: "numeric", month: "short", timeZone: "UTC" }).format(at).replace(/\./g, "");
  if (a.is_time_unset) return `${day} (por orden de llegada)`;

  const hours = at.getUTCHours();
  const minutes = String(at.getUTCMinutes()).padStart(2, "0");
  return `${day}, ${hours % 12 || 12}:${minutes} ${hours < 12 ? "a. m." : "p. m."}`;
}

/** The ISO string carries the clinic offset: read its wall-clock time as UTC to format it as-is. */
function shift(iso: string): Date {
  const match = iso.match(/^(\d{4}-\d{2}-\d{2}T\d{2}:\d{2})/);
  return new Date(`${match ? match[1] : iso.slice(0, 16)}:00Z`);
}

export type PortalDocumentSummary = { id: string; title: string; pet: string; clinic: string; to_sign: boolean; signed_at: string | null; created_at: string };

export type PortalDocument = {
  id: string;
  title: string;
  status: "signing" | "signed";
  to_sign: boolean;
  snapshot: {
    title: string;
    date: string;
    clinic: { name: string; tax_id: string | null; address: string | null };
    owner: { name: string };
    pet: { name: string; species: string | null; breed: string; sex: string | null; age: string; weight: string };
    vet: { name: string; license: string };
    blocks: import("./documents").DocumentBlock[];
  };
  signatures: { role: string; name: string }[];
};
