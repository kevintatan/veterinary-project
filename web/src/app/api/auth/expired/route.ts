import { endSession } from "@/lib/server/session";
import { NextResponse, type NextRequest } from "next/server";

/**
 * Clears the session cookie and sends the user to login with a reason
 * (pages cannot modify cookies, so they redirect here).
 */
export async function GET(request: NextRequest) {
  await endSession();

  const reason = request.nextUrl.searchParams.get("reason") === "forbidden" ? "forbidden" : "expired";

  return NextResponse.redirect(new URL(`/login?reason=${reason}`, request.url));
}
