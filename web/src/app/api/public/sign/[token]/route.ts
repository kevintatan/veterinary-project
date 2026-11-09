import { laravel, visitorHeaders } from "@/lib/server/laravel";
import { NextResponse } from "next/server";

/**
 * Remote signing (no session): forwards the owner's signature to the API with
 * their device and IP, which go into the evidence page of the signed PDF.
 */
export async function POST(request: Request, context: { params: Promise<{ token: string }> }) {
  const { token } = await context.params;
  if (!/^[A-Za-z0-9]{48}$/.test(token)) {
    return NextResponse.json({ message: "Enlace no válido." }, { status: 404 });
  }

  const body = await request.json().catch(() => ({}));
  const headers = await visitorHeaders();
  const userAgent = request.headers.get("user-agent");
  if (userAgent) headers["User-Agent"] = userAgent;

  const { status, data } = await laravel<{ data?: unknown; message?: string; errors?: Record<string, string[]> }>(`public/sign/${token}`, {
    method: "POST",
    body: { document_last4: body.document_last4, image: body.image, accepted: body.accepted },
    headers,
  });

  return NextResponse.json(data ?? {}, { status });
}
