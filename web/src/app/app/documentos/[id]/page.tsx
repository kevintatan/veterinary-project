import type { Metadata } from "next";
import DocumentView from "./DocumentView";

export const metadata: Metadata = { title: "Documento" };

export default async function Page({ params }: { params: Promise<{ id: string }> }) {
  const { id } = await params;
  return <DocumentView id={id} />;
}
