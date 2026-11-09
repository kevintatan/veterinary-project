import { laravel } from "@/lib/server/laravel";
import { getSessionToken, startSession } from "@/lib/server/session";
import { NextResponse } from "next/server";

/**
 * Accepts a clinic invitation and opens a session in that clinic.
 * - New person: creates the account (name + password) with the invited email.
 * - Existing user: must be signed in; joins with the current account.
 */
export async function POST(request: Request) {
  const body = await request.json().catch(() => ({}));
  const token = String(body.token ?? "");

  if (!/^[A-Za-z0-9]{48}$/.test(token)) {
    return NextResponse.json({ message: "La invitación no es válida." }, { status: 400 });
  }

  const existing = body.mode === "existing";
  const sessionToken = existing ? await getSessionToken() : undefined;

  if (existing && !sessionToken) {
    return NextResponse.json({ message: "Inicia sesión para aceptar la invitación." }, { status: 401 });
  }

  const { status, data } = await laravel<{ data?: { token: string }; message?: string; errors?: Record<string, string[]> }>(
    existing ? `invitations/${token}/accept-existing` : `invitations/${token}/accept`,
    {
      method: "POST",
      token: sessionToken,
      body: existing ? {} : { name: body.name, password: body.password, password_confirmation: body.password_confirmation },
    },
  );

  if (!data.data) {
    return NextResponse.json({ message: data.message ?? "No fue posible aceptar la invitación.", errors: data.errors ?? {} }, { status });
  }

  await startSession(data.data.token);

  return NextResponse.json({ ok: true });
}
