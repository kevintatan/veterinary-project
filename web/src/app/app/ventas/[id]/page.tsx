import type { Metadata } from "next";
import SaleView from "./SaleView";

export const metadata: Metadata = { title: "Venta" };

export default async function Page({ params }: { params: Promise<{ id: string }> }) {
  return <SaleView saleId={(await params).id} />;
}
