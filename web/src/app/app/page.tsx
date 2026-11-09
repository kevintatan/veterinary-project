import type { Metadata } from "next";
import TodayDashboard from "./TodayDashboard";

export const metadata: Metadata = { title: "Hoy en la clínica" };

export default function TodayPage() {
  return <TodayDashboard />;
}
