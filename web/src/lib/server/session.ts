import "server-only";

import type { ClinicOption } from "@/lib/types";
import { cookies } from "next/headers";

/**
 * The Laravel token never reaches the browser's JavaScript: it lives in an
 * httpOnly cookie and only the Next.js server reads it (info.md §4).
 */
export const SESSION_COOKIE = "zoo_session";

/** Short-lived token that can only choose a clinic (multi-clinic users). */
export const SELECTION_COOKIE = "zoo_select";

/** The user's clinics to choose from (names only, no secrets), read by the selection page. */
const SELECTION_CLINICS_COOKIE = "zoo_select_clinics";

const SESSION_MAX_AGE = 60 * 60 * 24 * 30; // = token TTL in the API (30 days)
const SELECTION_MAX_AGE = 60 * 10;

function cookieOptions(maxAge: number) {
  return {
    httpOnly: true,
    secure: process.env.NODE_ENV === "production",
    sameSite: "lax" as const,
    path: "/",
    maxAge,
  };
}

export async function getSessionToken(): Promise<string | undefined> {
  return (await cookies()).get(SESSION_COOKIE)?.value;
}

export async function getSelectionToken(): Promise<string | undefined> {
  return (await cookies()).get(SELECTION_COOKIE)?.value;
}

export async function startSession(token: string): Promise<void> {
  const jar = await cookies();
  jar.set(SESSION_COOKIE, token, cookieOptions(SESSION_MAX_AGE));
  jar.delete(SELECTION_COOKIE);
  jar.delete(SELECTION_CLINICS_COOKIE);
}

export async function startClinicSelection(token: string, clinics: ClinicOption[]): Promise<void> {
  const jar = await cookies();
  jar.set(SELECTION_COOKIE, token, cookieOptions(SELECTION_MAX_AGE));
  jar.set(SELECTION_CLINICS_COOKIE, JSON.stringify(clinics.map(({ id, name }) => ({ id, name }))), cookieOptions(SELECTION_MAX_AGE));
}

export async function getSelectionClinics(): Promise<ClinicOption[]> {
  try {
    return JSON.parse((await cookies()).get(SELECTION_CLINICS_COOKIE)?.value ?? "[]") as ClinicOption[];
  } catch {
    return [];
  }
}

export async function endSession(): Promise<void> {
  const jar = await cookies();
  jar.delete(SESSION_COOKIE);
  jar.delete(SELECTION_COOKIE);
  jar.delete(SELECTION_CLINICS_COOKIE);
}
