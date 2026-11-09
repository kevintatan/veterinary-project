import type { Labelled } from "./types";

/** Consents and authorizations signed on screen (docs/documentos.md). */
export type DocumentField = {
  key: string;
  label: string;
  type: "text" | "textarea" | "select" | "checkbox" | "money" | "number";
  required?: boolean;
  options?: Record<string, string>;
  help?: string;
  placeholder?: string;
};

export type DocumentTypeDef = {
  code: string;
  name: string;
  short: string;
  description: string;
  fields: DocumentField[];
  signers: { role: SignerRole; label: string; required: boolean }[];
  allows_witness: boolean;
};

export type SignerRole = "owner" | "vet" | "witness";

export type DocumentSummary = {
  id: string;
  type: string;
  title: string;
  short: string;
  status: Labelled<"draft" | "signing" | "signed" | "voided">;
  vet: { id: string; name: string } | null;
  signatures: number;
  signers: number;
  pending: SignerRole[];
  created_at: string;
  signed_at: string | null;
  voided_at: string | null;
};

export type DocumentBlock = { type: "p"; text: string } | { type: "list"; items: string[] };

export type PetDocument = DocumentSummary & {
  pet_id: string;
  owner_id: string;
  data: Record<string, string | number | boolean | null>;
  with_witness: boolean;
  snapshot: {
    title: string;
    date: string;
    clinic: { name: string; legal_name: string | null; tax_id: string | null; address: string | null; phone: string | null };
    owner: { name: string; document: string; phone: string };
    pet: { name: string; species: string | null; breed: string; sex: string | null; age: string; weight: string; microchip: string | null };
    vet: { id: string; name: string; license: string };
    signers: Partial<Record<SignerRole, { label: string; required: boolean }>>;
    blocks: DocumentBlock[];
  };
  created_by: { id: string; name: string } | null;
  signature_list: { role: SignerRole; name: string; document: string | null; method: string; method_label: string; signed_at: string; image_url: string }[];
  /** Pending link sent to the owner to sign remotely (D3). */
  remote: { channel: "whatsapp" | "email" | "link"; sent_at: string; expires_at: string; active: boolean; attempts: number } | null;
  owner_contact: { has_email: boolean; has_phone: boolean };
  content_hash: string | null;
  pdf_sha256: string | null;
  void_reason: string | null;
  can: { edit: boolean; sign_as_vet: boolean; send_remote: boolean; void: boolean };
};

export const STATUS_TONE: Record<PetDocument["status"]["value"], string> = {
  draft: "bg-gray-100 text-gray-700 dark:bg-white/5 dark:text-gray-300",
  signing: "bg-warning-50 text-warning-700 dark:bg-warning-500/10 dark:text-warning-400",
  signed: "bg-success-50 text-success-700 dark:bg-success-500/10 dark:text-success-400",
  voided: "bg-error-50 text-error-700 dark:bg-error-500/10 dark:text-error-400",
};
