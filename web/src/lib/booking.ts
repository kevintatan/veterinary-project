import { api } from "@/lib/api";
import { useQuery } from "@tanstack/react-query";

/** Online booking from the clinic's side (docs/reservas.md). */

export type BookingRequest = {
  id: string;
  starts_at: string;
  ends_at: string;
  is_past: boolean;
  service: { id: string; name: string } | null;
  branch: { id: string; name: string } | null;
  professional: { id: string; name: string } | null;
  details: { name: string; phone: string; document_type: string | null; document_number: string | null; pet_name: string; species_name: string; notes: string | null; professional_chosen: boolean; requested_at: string };
  suggestions: { id: string; full_name: string; mobile_phone: string | null; document: string | null; match: "both" | "document" | "phone"; pets: { id: string; name: string; same_name: boolean }[] }[];
};

export type OnlineBookingSettings = {
  enabled: boolean;
  min_notice_hours: number;
  max_days_ahead: number;
  choose_professional: boolean;
  message: string | null;
  included_in_plan: boolean;
  public_path: string;
  public_url: string;
};

export const bookingKeys = {
  all: ["booking-requests"] as const,
  list: ["booking-requests", "list"] as const,
  count: ["booking-requests", "count"] as const,
  settings: ["online-booking", "settings"] as const,
};

/** Pending requests (sidebar badge, agenda button). Shares the cache everywhere. */
export function useBookingRequestCount(enabled: boolean) {
  return useQuery({
    queryKey: bookingKeys.count,
    queryFn: () => api<{ data: { pending: number } }>("booking-requests/count").then((r) => r.data.pending),
    enabled,
    refetchInterval: 20_000, // same rhythm as the bell (paused while the tab is hidden)
  });
}
