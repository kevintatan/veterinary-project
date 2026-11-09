import "server-only";

import { cookies } from "next/headers";

/**
 * Owner portal session (Fase 4e). Separate from the staff session (zoo_session):
 * another cookie, another kind of token (ability "tutor", no clinic). Same rule:
 * the token only lives in an httpOnly cookie, never in the browser's JavaScript.
 */
export const TUTOR_COOKIE = "zoo_tutor";

const MAX_AGE = 60 * 60 * 24 * 180; // = TutorAccount::TOKEN_DAYS in the API

export async function getTutorToken(): Promise<string | undefined> {
  return (await cookies()).get(TUTOR_COOKIE)?.value;
}

export async function startTutorSession(token: string): Promise<void> {
  (await cookies()).set(TUTOR_COOKIE, token, {
    httpOnly: true,
    secure: process.env.NODE_ENV === "production",
    sameSite: "lax",
    path: "/",
    maxAge: MAX_AGE,
  });
}

export async function endTutorSession(): Promise<void> {
  (await cookies()).delete(TUTOR_COOKIE);
}
