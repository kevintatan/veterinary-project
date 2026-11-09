import type { Metadata } from "next";
import SalesView from "./SalesView";

export const metadata: Metadata = { title: "Ventas" };

export default function Page() {
  return <SalesView />;
}
