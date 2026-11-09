import type { Metadata } from "next";
import { Suspense } from "react";
import OwnerProfile from "./OwnerProfile";

export const metadata: Metadata = { title: "Ficha del tutor" };

export default async function Page({ params }: { params: Promise<{ id: string }> }) {
  const { id } = await params;

  return (
    <Suspense>
      <OwnerProfile id={id} />
    </Suspense>
  );
}
