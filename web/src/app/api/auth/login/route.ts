import { laravel } from "@/lib/server/laravel";
import { startClinicSelection, startSession } from "@/lib/server/session";
import { NextResponse } from "next/server";

type LoginData = {
  data?: {
    token: string;
    requires_clinic_selection: boolean;
    clinics?: { id: string; name: string; logo_path: string | null }[];
  };
  message?: string;
  errors?: Record<string, string[]>;
};

/**
 * Logs in against Laravel and keeps the token in an httpOnly cookie.
 * The response never includes the token.
 */
export async function POST(request: Request) {
  const body = await request.json().catch(() => ({}));
  const { status, data } = await laravel<LoginData>("auth/login", {
    method: "POST",
    body: { email: body.email, password: body.password, device_name: "web" },
  });

  if (status !== 200 || !data.data) {
    return NextResponse.json({ message: data.message, errors: data.errors }, { status });
  }

  if (data.data.requires_clinic_selection) {
    await startClinicSelection(data.data.token, data.data.clinics ?? []);

    return NextResponse.json({ requires_clinic_selection: true });
  }

  await startSession(data.data.token);

  return NextResponse.json({ requires_clinic_selection: false });
}
