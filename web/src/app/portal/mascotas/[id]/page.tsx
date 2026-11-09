import { type PortalBookingClinic, type PortalMe, type PortalPet } from "@/lib/portal";
import { portalApi } from "@/lib/server/portal";
import type { Metadata } from "next";
import PortalHeader from "../../PortalHeader";
import PetView from "./PetView";

export const dynamic = "force-dynamic";
export const metadata: Metadata = { title: "Carné" };

export default async function Page({ params }: { params: Promise<{ id: string }> }) {
  const { id } = await params;
  const [me, pet, booking] = await Promise.all([portalApi<PortalMe>("me"), portalApi<PortalPet>(`pets/${id}`), portalApi<PortalBookingClinic[]>("booking")]);
  const bookFor = booking.flatMap((c) => c.pets).find((p) => p.id === id) ?? null;

  return (
    <>
      <PortalHeader name={me.name} back={{ href: "/portal", label: "Mis mascotas" }} />
      <PetView pet={pet} bookFor={bookFor} />
    </>
  );
}
