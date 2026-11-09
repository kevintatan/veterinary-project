import { laravel } from "@/lib/server/laravel";
import type { Metadata } from "next";
import { notFound } from "next/navigation";
import BookingFlow, { type BookingInfo } from "./BookingFlow";

type Props = { params: Promise<{ slug: string }> };

async function info(slug: string): Promise<BookingInfo | null> {
  if (!/^[a-z0-9-]{1,80}$/.test(slug)) return null;
  const { status, data } = await laravel<{ data: BookingInfo }>(`public/booking/${slug}`);
  return status === 200 ? data.data : null;
}

export async function generateMetadata({ params }: Props): Promise<Metadata> {
  const data = await info((await params).slug);
  return data
    ? { title: { absolute: `Pide tu cita · ${data.clinic.name}` }, description: `Pide una cita en ${data.clinic.name} en línea: elige el servicio, el día y la hora.` }
    : { title: "Reserva no disponible" };
}

/** Public booking page of a clinic (docs/reservas.md). */
export default async function Page({ params }: Props) {
  const data = await info((await params).slug);
  if (!data) notFound();

  return <BookingFlow info={data} />;
}
