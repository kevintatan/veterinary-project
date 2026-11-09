import { apiUrl } from "@/lib/server/laravel";
import { NextResponse } from "next/server";

/**
 * Photo of the public pet card (no session): only valid card tokens, streamed
 * from the API. The API decides whether the page (and so the photo) is public.
 */
export async function GET(_request: Request, context: { params: Promise<{ token: string }> }) {
  const { token } = await context.params;

  if (!/^[A-Za-z0-9]{32}$/.test(token)) {
    return new NextResponse(null, { status: 404 });
  }

  const upstream = await fetch(apiUrl(`public/pets/${token}/photo`), { cache: "no-store" });

  if (!upstream.ok || !upstream.body) {
    return new NextResponse(null, { status: 404 });
  }

  return new NextResponse(upstream.body, {
    headers: {
      "Content-Type": upstream.headers.get("content-type") ?? "image/webp",
      "Cache-Control": "public, max-age=86400",
    },
  });
}
