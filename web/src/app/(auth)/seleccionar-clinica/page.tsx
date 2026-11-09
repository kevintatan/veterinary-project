import { getSelectionClinics } from "@/lib/server/session";
import type { Metadata } from "next";
import { redirect } from "next/navigation";
import ClinicChooser from "./ClinicChooser";

export const metadata: Metadata = { title: "Elige una clínica" };

/**
 * Users who work in several clinics choose one after logging in.
 * Each clinic gets its own token: data never mixes between clinics.
 */
export default async function SelectClinicPage() {
  const clinics = await getSelectionClinics();

  if (clinics.length === 0) {
    redirect("/login?reason=expired");
  }

  return (
    <div>
      <div className="mb-8">
        <h1 className="mb-2 text-title-sm font-semibold text-gray-800 dark:text-white/90">Elige una clínica</h1>
        <p className="text-sm text-gray-500 dark:text-gray-400">Trabajas en varias clínicas. ¿En cuál quieres entrar?</p>
      </div>
      <ClinicChooser clinics={clinics} />
    </div>
  );
}
