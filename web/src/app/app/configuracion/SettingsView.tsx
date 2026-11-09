"use client";

import PageHeader from "@/components/common/PageHeader";
import { useSession } from "@/components/providers/SessionProvider";
import { cn } from "@/utils";
import Link from "next/link";
import { useSearchParams } from "next/navigation";
import BillingSection from "./sections/BillingSection";
import BlocksSection from "./sections/BlocksSection";
import BranchesSection from "./sections/BranchesSection";
import ClinicSection from "./sections/ClinicSection";
import DocumentsSection from "./sections/DocumentsSection";
import OnlineBookingSection from "./sections/OnlineBookingSection";
import ProfileSection from "./sections/ProfileSection";
import RemindersSection from "./sections/RemindersSection";
import ScheduleSection from "./sections/ScheduleSection";
import ServicesSection from "./sections/ServicesSection";
import TeamSection from "./sections/TeamSection";

type Section = { key: string; label: string; description: string; permission: string | null; render: () => React.ReactNode };

const SECTIONS: Section[] = [
  { key: "clinica", label: "Clínica", description: "Nombre, NIT, logo y reglas generales.", permission: "clinic.settings.manage", render: () => <ClinicSection /> },
  { key: "sedes", label: "Sedes", description: "Direcciones y teléfonos de cada sede.", permission: "branches.manage", render: () => <BranchesSection /> },
  { key: "equipo", label: "Equipo", description: "Personas, roles e invitaciones.", permission: "members.view", render: () => <TeamSection /> },
  { key: "horarios", label: "Horarios", description: "Horario de atención por sede y por profesional.", permission: "agenda.settings.manage", render: () => <ScheduleSection /> },
  { key: "servicios", label: "Servicios", description: "Lo que se agenda: duración y precio.", permission: "agenda.settings.manage", render: () => <ServicesSection /> },
  { key: "reservas", label: "Reserva en línea", description: "Tu enlace para que los clientes pidan citas; tu equipo las acepta o rechaza.", permission: "clinic.settings.manage", render: () => <OnlineBookingSection /> },
  { key: "recordatorios", label: "Recordatorios", description: "Mensajes automáticos por WhatsApp: cuándo y a quién.", permission: "appointments.view", render: () => <RemindersSection /> },
  { key: "documentos", label: "Documentos", description: "Texto de los consentimientos y autorizaciones que se firman en pantalla.", permission: "clinic.settings.manage", render: () => <DocumentsSection /> },
  { key: "bloqueos", label: "Bloqueos", description: "Festivos, vacaciones y cierres.", permission: "agenda.settings.manage", render: () => <BlocksSection /> },
  { key: "plan", label: "Plan y facturación", description: "Tu plan, el uso del mes y los planes disponibles.", permission: "clinic.settings.manage", render: () => <BillingSection /> },
  { key: "perfil", label: "Mi perfil", description: "Tus datos, tarjeta profesional y contraseña.", permission: null, render: () => <ProfileSection /> },
];

export default function SettingsView() {
  const { permissions } = useSession();
  const params = useSearchParams();
  const available = SECTIONS.filter((s) => s.permission === null || permissions.includes(s.permission));
  const active = available.find((s) => s.key === params.get("seccion")) ?? available[0];

  return (
    <>
      <PageHeader title="Configuración" subtitle={active.description} />
      <div className="grid gap-6 lg:grid-cols-[220px_minmax(0,1fr)]">
        <nav aria-label="Secciones de configuración" className="flex gap-1 overflow-x-auto lg:flex-col">
          {available.map((s) => (
            <Link
              key={s.key}
              href={`/app/configuracion?seccion=${s.key}`}
              scroll={false}
              aria-current={s.key === active.key ? "page" : undefined}
              className={cn(
                "shrink-0 rounded-lg px-3 py-2 text-theme-sm font-medium whitespace-nowrap transition",
                s.key === active.key
                  ? "bg-brand-50 text-brand-700 dark:bg-brand-500/15 dark:text-brand-300"
                  : "text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-white",
              )}
            >
              {s.label}
            </Link>
          ))}
        </nav>
        <div className="min-w-0">{active.render()}</div>
      </div>
    </>
  );
}
