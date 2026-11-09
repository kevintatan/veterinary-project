import { apiUrl } from "@/lib/server/laravel";
import { endTutorSession, getTutorToken } from "@/lib/server/tutor-session";
import { NextResponse, type NextRequest } from "next/server";

/**
 * Owner portal BFF (Fase 4e): browser → /api/tutor/portal/<path> → Laravel
 * /api/v1/portal/<path>, with the token of the zoo_tutor cookie. It can only
 * reach the portal routes of the API (anything else → 404).
 */
const FORWARDED_RESPONSE_HEADERS = ["content-type", "content-disposition", "content-length", "cache-control"];

async function forward(request: NextRequest, context: { params: Promise<{ path: string[] }> }) {
  const { path } = await context.params;

  if (path[0] !== "portal" || path.some((segment) => segment === "." || segment === ".." || segment === "")) {
    return NextResponse.json({ message: "Ruta no válida." }, { status: 404 });
  }

  const token = await getTutorToken();
  if (!token) {
    return NextResponse.json({ message: "Inicia sesión en el portal." }, { status: 401 });
  }

  const origin = request.headers.get("origin");
  if (!["GET", "HEAD"].includes(request.method) && origin && new URL(origin).host !== request.nextUrl.host) {
    return NextResponse.json({ message: "Origen no permitido." }, { status: 403 });
  }

  const target = new URL(apiUrl(path.map(encodeURIComponent).join("/")));
  target.search = request.nextUrl.search;

  const hasBody = !["GET", "HEAD"].includes(request.method);
  const upstream = await fetch(target, {
    method: request.method,
    headers: {
      Accept: "application/json",
      Authorization: `Bearer ${token}`,
      ...(hasBody ? { "Content-Type": "application/json" } : {}),
      // The owner's device and IP: evidence when they sign a consent, and per-person rate limits.
      ...(request.headers.get("user-agent") ? { "User-Agent": request.headers.get("user-agent")! } : {}),
      ...(request.headers.get("x-forwarded-for") ? { "X-Forwarded-For": request.headers.get("x-forwarded-for")! } : {}),
    },
    body: hasBody ? await request.text() : undefined,
    cache: "no-store",
  });

  if (upstream.status === 401) {
    await endTutorSession();
  }

  const headers = new Headers();
  for (const name of FORWARDED_RESPONSE_HEADERS) {
    const value = upstream.headers.get(name);
    if (value) headers.set(name, value);
  }

  return new NextResponse(upstream.body, { status: upstream.status, headers });
}

export const GET = forward;
export const POST = forward;
