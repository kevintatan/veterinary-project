import { laravel, visitorHeaders } from "@/lib/server/laravel";
import { startSession } from "@/lib/server/session";
import { NextResponse } from "next/server";

type RegisterData = { data?: { token: string }; message?: string; errors?: Record<string, string[]> };

/**
 * New clinic sign-up: creates the clinic, its admin and the 15-day trial in
 * Laravel and opens the session (token in the httpOnly cookie, never in the
 * browser). Forwards the visitor's IP: sign-ups are rate limited per person.
 */
export async function POST(request: Request) {
  const body = await request.json().catch(() => ({}));

  const { status, data } = await laravel<RegisterData>("auth/register", {
    method: "POST",
    body: {
      clinic_name: body.clinic_name,
      name: body.name,
      email: body.email,
      phone: body.phone || null,
      password: body.password,
      password_confirmation: body.password_confirmation,
      accept_terms: body.accept_terms === true,
      device_name: "web",
    },
    headers: await visitorHeaders(),
  });

  if (status !== 201 || !data.data) {
    return NextResponse.json({ message: data.message, errors: data.errors }, { status });
  }

  await startSession(data.data.token);

  return NextResponse.json({ ok: true }, { status: 201 });
}
