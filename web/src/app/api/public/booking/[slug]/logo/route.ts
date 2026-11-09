import { apiUrl } from "@/lib/server/laravel";

/** The clinic's logo on its public booking page. */
export async function GET(_request: Request, context: { params: Promise<{ slug: string }> }) {
  const { slug } = await context.params;
  if (!/^[a-z0-9-]{1,80}$/.test(slug)) return new Response(null, { status: 404 });

  const upstream = await fetch(apiUrl(`public/booking/${slug}/logo`), { cache: "no-store" });
  if (!upstream.ok) return new Response(null, { status: upstream.status });

  return new Response(upstream.body, {
    headers: { "Content-Type": upstream.headers.get("content-type") ?? "image/png", "Cache-Control": "public, max-age=86400" },
  });
}
