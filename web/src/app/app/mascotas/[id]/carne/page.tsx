import type { Metadata } from "next";
import CardDesigner from "./CardDesigner";

export const metadata: Metadata = { title: "Carné de la mascota" };

export default async function Page({ params }: { params: Promise<{ id: string }> }) {
  const { id } = await params;

  return <CardDesigner petId={id} />;
}
