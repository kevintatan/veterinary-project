import { apiUrl } from "@/lib/server/laravel";
import { endSession, getSessionToken } from "@/lib/server/session";
import { NextResponse, type NextRequest } from "next/server";

/**
 * BFF proxy: browser → /api/backend/<path> → Laravel /api/v1/<path>,
 * adding the token from the httpOnly cookie. Only paths under API_URL can be
 * reached (no open proxy). Streams bodies both ways (JSON, uploads, downloads).
 */
const FORWARDED_RESPONSE_HEADERS = ["content-type", "content-disposition", "content-length", "cache-control"];

async function forward(request: NextRequest, context: { params: Promise<{ path: string[] }> }) {
  const token = await getSessionToken();

  if (!token) {
    return NextResponse.json({ message: "Tu sesión expiró. Ingresa de nuevo." }, { status: 401 });
  }

  // CSRF defense in depth (cookies are already SameSite=Lax): writes must come from our own origin.
  const origin = request.headers.get("origin");
  if (!["GET", "HEAD"].includes(request.method) && origin && new URL(origin).host !== request.nextUrl.host) {
    return NextResponse.json({ message: "Origen no permitido." }, { status: 403 });
  }

  const { path } = await context.params;

  // "." / ".." would be resolved by URL() and could escape API_URL.
  if (path.some((segment) => segment === "." || segment === ".." || segment === "")) {
    return NextResponse.json({ message: "Ruta no válida." }, { status: 400 });
  }

  const target = new URL(apiUrl(path.map(encodeURIComponent).join("/")));
  target.search = request.nextUrl.search;

  const headers = new Headers({ Accept: "application/json", Authorization: `Bearer ${token}` });
  // The visitor's device and IP (Laravel trusts them only from TRUSTED_PROXIES): evidence of
  // electronic signatures and per-person rate limits, instead of this server's.
  const userAgent = request.headers.get("user-agent");
  if (userAgent) headers.set("User-Agent", userAgent);
  const forwardedFor = request.headers.get("x-forwarded-for") ?? request.headers.get("x-real-ip");
  if (forwardedFor) headers.set("X-Forwarded-For", forwardedFor);
  const contentType = request.headers.get("content-type");
  if (contentType) {
    headers.set("Content-Type", contentType);
  }

  const hasBody = !["GET", "HEAD"].includes(request.method);
  const upstream = await fetch(target, {
    method: request.method,
    headers,
    body: hasBody ? request.body : undefined,
    // Required by Node's fetch to stream a request body.
    ...(hasBody ? { duplex: "half" } : {}),
    cache: "no-store",
  } as RequestInit);

  // Token revoked or expired in Laravel: drop the cookie so the UI goes to login.
  if (upstream.status === 401) {
    await endSession();
  }

  const responseHeaders = new Headers();
  for (const name of FORWARDED_RESPONSE_HEADERS) {
    const value = upstream.headers.get(name);
    if (value) {
      responseHeaders.set(name, value);
    }
  }

  return new NextResponse(upstream.body, { status: upstream.status, headers: responseHeaders });
}

export const GET = forward;
export const POST = forward;
export const PATCH = forward;
export const PUT = forward;
export const DELETE = forward;
