import { laravel, visitorHeaders } from "@/lib/server/laravel";
import { NextResponse } from "next/server";

/**
 * Public online booking (no session, docs/reservas.md): the page's info and
 * sending a request. Forwards the visitor's IP: requests are rate limited per person.
 */
const SLUG = /^[a-z0-9-]{1,80}$/;

export async function GET(_request: Request, context: { params: Promise<{ slug: string }> }) {
  const { slug } = await context.params;
  if (!SLUG.test(slug)) return NextResponse.json({ message: "Clínica no encontrada." }, { status: 404 });

  const { status, data } = await laravel(`public/booking/${slug}`, { headers: await visitorHeaders() });
  return NextResponse.json(data ?? {}, { status });
}

export async function POST(request: Request, context: { params: Promise<{ slug: string }> }) {
  const { slug } = await context.params;
  if (!SLUG.test(slug)) return NextResponse.json({ message: "Clínica no encontrada." }, { status: 404 });

  const body = await request.json().catch(() => ({}));
  const fields = ["branch_id", "service_id", "starts_at", "user_id", "name", "phone", "document_type", "document_number", "pet_name", "species_id", "notes", "accept_data", "accept_whatsapp"];
  const { status, data } = await laravel(`public/booking/${slug}`, {
    method: "POST",
    body: Object.fromEntries(fields.map((f) => [f, body[f] ?? null])),
    headers: await visitorHeaders(),
  });
  return NextResponse.json(data ?? {}, { status });
}
