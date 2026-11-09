import { laravel, visitorHeaders } from "@/lib/server/laravel";
import { NextResponse } from "next/server";

/** Free times of a day for the public booking page (docs/reservas.md). */
export async function GET(request: Request, context: { params: Promise<{ slug: string }> }) {
  const { slug } = await context.params;
  if (!/^[a-z0-9-]{1,80}$/.test(slug)) return NextResponse.json({ message: "Clínica no encontrada." }, { status: 404 });

  const incoming = new URL(request.url).searchParams;
  const query = new URLSearchParams();
  for (const key of ["branch_id", "service_id", "date", "user_id"]) {
    const value = incoming.get(key);
    if (value) query.set(key, value);
  }

  const { status, data } = await laravel(`public/booking/${slug}/slots?${query}`, { headers: await visitorHeaders() });
  return NextResponse.json(data ?? {}, { status });
}
