import { laravel } from "@/lib/server/laravel";
import { endSession, getSessionToken } from "@/lib/server/session";
import { NextResponse } from "next/server";

export async function POST() {
  const token = await getSessionToken();

  if (token) {
    // Revoke the token in Laravel too; the cookie is cleared regardless.
    await laravel("auth/logout", { method: "POST", token }).catch(() => null);
  }

  await endSession();

  return NextResponse.json({ ok: true });
}
