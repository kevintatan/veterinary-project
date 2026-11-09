import type { Metadata } from "next";
import PetProfile from "./PetProfile";

export const metadata: Metadata = { title: "Ficha de la mascota" };

export default async function Page({ params }: { params: Promise<{ id: string }> }) {
  const { id } = await params;

  return <PetProfile id={id} />;
}
