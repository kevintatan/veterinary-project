import { NextResponse, type NextRequest } from "next/server";

/**
 * Route guard (Next.js 16 "proxy", formerly middleware).
 * Only checks that a session cookie exists; Laravel validates the token on every call.
 */
const SESSION_COOKIE = "zoo_session";
const SELECTION_COOKIE = "zoo_select";
const TUTOR_COOKIE = "zoo_tutor"; // owner portal (Fase 4e): a separate session

export function proxy(request: NextRequest) {
  const { pathname, search } = request.nextUrl;
  const hasSession = request.cookies.has(SESSION_COOKIE);

  if ((pathname.startsWith("/app") || pathname.startsWith("/imprimir")) && !hasSession) {
    const login = new URL("/login", request.url);
    login.searchParams.set("next", pathname + search);

    return NextResponse.redirect(login);
  }

  if (pathname === "/seleccionar-clinica" && !request.cookies.has(SELECTION_COOKIE) && !hasSession) {
    return NextResponse.redirect(new URL("/login", request.url));
  }

  // Owner portal: everything needs its session except signing in and the invitation link.
  if (pathname.startsWith("/portal")) {
    const hasTutor = request.cookies.has(TUTOR_COOKIE);
    const open = pathname === "/portal/ingresar" || pathname === "/portal/recuperar" || pathname.startsWith("/portal/activar/");

    if (!open && !hasTutor) {
      const login = new URL("/portal/ingresar", request.url);
      if (pathname !== "/portal") login.searchParams.set("next", pathname);
      return NextResponse.redirect(login);
    }

    if (pathname === "/portal/ingresar" && hasTutor) {
      return NextResponse.redirect(new URL("/portal", request.url));
    }

    return NextResponse.next();
  }

  if ((pathname === "/login" || pathname === "/registro") && hasSession) {
    return NextResponse.redirect(new URL("/app", request.url));
  }

  return NextResponse.next();
}

export const config = {
  matcher: ["/app/:path*", "/imprimir/:path*", "/login", "/registro", "/seleccionar-clinica", "/portal/:path*"],
};
