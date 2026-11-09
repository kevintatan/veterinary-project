/** Shapes returned by the Laravel API (api/app/Http/Resources). */

export type Me = {
  user: { id: string; name: string; email: string; phone: string | null; professional_license: string | null };
  clinic: { id: string; name: string; slug: string; logo_url: string | null; timezone: string };
  role: { name: string; label: string } | null;
  permissions: string[];
  branches: { id: string; name: string }[];
  subscription: {
    status: string;
    plan: { code: string; name: string };
    trial_ends_at: string | null;
    trial_days_left: number | null;
    current_period_end: string | null;
    allows_writes: boolean;
  } | null;
};

export type ClinicOption = { id: string; name: string; logo_path?: string | null };

export type Labelled<T extends string = string> = { value: T; label: string };

export type AppointmentStatus =
  | "requested"
  | "scheduled"
  | "confirmed"
  | "checked_in"
  | "in_progress"
  | "completed"
  | "cancelled"
  | "no_show";

export type Appointment = {
  id: string;
  branch_id: string;
  starts_at: string;
  ends_at: string;
  is_time_unset: boolean;
  is_home_visit: boolean;
  status: Labelled<AppointmentStatus>;
  allowed_next_statuses: Labelled<AppointmentStatus>[];
  notes_for_owner: string | null;
  internal_notes: string | null;
  service: { id: string; name: string; color: string | null } | null;
  pet: { id: string; name: string; species: string | null } | null;
  owner: { id: string; full_name: string; mobile_phone: string | null } | null;
  users: { id: string; name: string; role: "primary" | "assistant" }[];
  /** Online request waiting for the clinic (status "requested"). */
  booking?: { name: string; phone: string; document_type: string | null; document_number: string | null; pet_name: string; species_name: string; notes: string | null } | null;
};

export type Task = {
  id: string;
  title: string;
  description: string | null;
  status: Labelled<"pending" | "done" | "discarded">;
  is_overdue: boolean;
  due_at: string | null;
  assignee: { id: string; name: string } | null;
  created_by: { id: string; name: string } | null;
  pet: { id: string; name: string } | null;
  owner: { id: string; full_name: string } | null;
  completed_at: string | null;
  can_edit: boolean;
  created_at: string;
};

export type Paginated<T> = { data: T[]; meta: { current_page: number; last_page: number; total: number } };

export type Professional = { id: string; name: string; agenda_color: string | null };

export type Service = {
  id: string;
  name: string;
  category: Labelled;
  duration_minutes: number;
  price: number;
  color: string | null;
  is_active: boolean;
};

export type ScheduleRange = { id: string; user_id: string | null; weekday: number; start_time: string; end_time: string };

export type ScheduleBlock = { id: string; branch_id: string | null; user_id: string | null; starts_at: string; ends_at: string; reason: string };

export type PetSummary = { id: string; name: string; species?: { id: string; name: string } | null; breed?: { id: string; name: string } | null };

export type OwnerWithPets = {
  id: string;
  full_name: string;
  document_number: string | null;
  mobile_phone: string | null;
  pets: PetSummary[];
};

export type AppointmentDetail = Appointment & {
  status_history: { from: string | null; to: string; label: string; channel: string; changed_by: string | null; note: string | null; at: string }[];
  cancellation_reason: string | null;
  home_address: string | null;
};

export type PetAlert = {
  id: string;
  type: Labelled<"infectious_disease" | "chronic_condition" | "allergy" | "behavior" | "other">;
  description: string;
  severity: Labelled<"low" | "medium" | "high">;
  is_active: boolean;
};

export type PetDetail = {
  id: string;
  name: string;
  species: { id: string; name: string; code: string } | null;
  breed: { id: string; name: string } | null;
  breed_other: string | null;
  sex: Labelled | null;
  age: string | null;
  weight_kg: number | null;
  photo_url: string | null;
  alerts: PetAlert[];
  owner?: { id: string; full_name: string; mobile_phone: string | null };
};
