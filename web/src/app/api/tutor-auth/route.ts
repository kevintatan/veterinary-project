import { laravel, visitorHeaders } from "@/lib/server/laravel";
import { endTutorSession, getTutorToken, startTutorSession } from "@/lib/server/tutor-session";
import { NextResponse } from "next/server";

type Session = { data?: { token: string }; message?: string; errors?: Record<string, string[]> };

/**
 * Owner portal session (Fase 4e). One endpoint, four actions:
 *  - login:    email + password
 *  - activate: create the account from a clinic invitation (first time)
 *  - link:     an already signed-in owner adds another clinic's invitation
 *  - forgot:   send a password code by email (same answer whether the email exists or not)
 *  - reset:    code + new password → signed in
 *  - logout
 * The token goes to the httpOnly cookie and never to the browser.
 */
export async function POST(request: Request) {
  const body = await request.json().catch(() => ({}));
  const forwarded = await visitorHeaders();
  const token = typeof body.token === "string" && /^[A-Za-z0-9]{48}$/.test(body.token) ? body.token : null;

  switch (body.action) {
    case "login": {
      const { status, data } = await laravel<Session>("portal/login", {
        method: "POST",
        body: { email: body.email, password: body.password, device_name: "portal-web" },
        headers: forwarded,
      });
      if (status !== 200 || !data.data) return NextResponse.json({ message: data.message, errors: data.errors }, { status });
      await startTutorSession(data.data.token);
      return NextResponse.json({ ok: true });
    }

    case "activate": {
      if (!token) return NextResponse.json({ message: "Invitación no válida." }, { status: 404 });
      const { status, data } = await laravel<Session>(`portal/invitations/${token}/accept`, {
        method: "POST",
        body: { name: body.name, email: body.email, password: body.password, password_confirmation: body.password_confirmation, device_name: "portal-web" },
        headers: forwarded,
      });
      if (status !== 201 || !data.data) return NextResponse.json({ message: data.message, errors: data.errors }, { status });
      await startTutorSession(data.data.token);
      return NextResponse.json({ ok: true });
    }

    case "link": {
      const session = await getTutorToken();
      if (!token || !session) return NextResponse.json({ message: "Inicia sesión primero." }, { status: 401 });
      const { status, data } = await laravel<Session>(`portal/invitations/${token}/link`, { method: "POST", token: session, headers: forwarded });
      return NextResponse.json(status === 200 ? { ok: true } : { message: data.message, errors: data.errors }, { status });
    }

    case "forgot": {
      const { status, data } = await laravel<{ data?: { message: string }; message?: string; errors?: Record<string, string[]> }>("portal/password/forgot", {
        method: "POST",
        body: { email: body.email },
        headers: forwarded,
      });
      return NextResponse.json(status === 200 ? { ok: true, message: data.data?.message } : { message: data.message, errors: data.errors }, { status });
    }

    case "reset": {
      const { status, data } = await laravel<Session>("portal/password/reset", {
        method: "POST",
        body: { email: body.email, code: body.code, password: body.password, password_confirmation: body.password_confirmation, device_name: "portal-web" },
        headers: forwarded,
      });
      if (status !== 200 || !data.data) return NextResponse.json({ message: data.message, errors: data.errors }, { status });
      await startTutorSession(data.data.token);
      return NextResponse.json({ ok: true });
    }

    case "logout": {
      const session = await getTutorToken();
      if (session) await laravel("portal/logout", { method: "POST", token: session });
      await endTutorSession();
      return NextResponse.json({ ok: true });
    }

    default:
      return NextResponse.json({ message: "Acción no válida." }, { status: 400 });
  }
}
