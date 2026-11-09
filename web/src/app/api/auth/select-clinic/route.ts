import { laravel } from "@/lib/server/laravel";
import { getSelectionToken, getSessionToken, startSession } from "@/lib/server/session";
import { NextResponse } from "next/server";

/**
 * Exchanges the selection token (after login) or the current clinic token
 * (switching clinic) for a token bound to the chosen clinic.
 */
export async function POST(request: Request) {
  const { clinic_id: clinicId } = await request.json().catch(() => ({}));
  const selectionToken = await getSelectionToken();
  const sessionToken = await getSessionToken();

  const [path, token] = selectionToken
    ? ["auth/select-clinic", selectionToken]
    : ["auth/switch-clinic", sessionToken];

  if (!token) {
    return NextResponse.json({ message: "Tu sesión expiró. Ingresa de nuevo." }, { status: 401 });
  }

  const { status, data } = await laravel<{ data?: { token: string }; message?: string }>(path, {
    method: "POST",
    token,
    body: { clinic_id: clinicId, device_name: "web" },
  });

  if (status !== 200 || !data.data) {
    return NextResponse.json({ message: data.message ?? "No fue posible abrir la clínica." }, { status });
  }

  await startSession(data.data.token);

  return NextResponse.json({ ok: true });
}
