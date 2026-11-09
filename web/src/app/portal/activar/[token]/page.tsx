import { Logo } from "@/components/brand/Logo";
import { laravel, visitorHeaders } from "@/lib/server/laravel";
import { getTutorToken } from "@/lib/server/tutor-session";
import type { Metadata } from "next";
import Link from "next/link";
import { notFound } from "next/navigation";
import { ActivateForm, LinkButton } from "../../PortalForm";

export const metadata: Metadata = { title: "Activa tu acceso" };
export const dynamic = "force-dynamic";

type Invitation = {
  clinic: { name: string };
  owner: { first_name: string; full_name: string; email: string | null };
  phone_hint: string;
  pets: string[];
  account_exists: boolean;
};

/**
 * Landing of the clinic's invitation link (Fase 4e). First time: create the
 * access once. Already has an account: sign in and add this clinic.
 */
export default async function Page({ params }: { params: Promise<{ token: string }> }) {
  const { token } = await params;
  if (!/^[A-Za-z0-9]{48}$/.test(token)) notFound();

  const { status, data } = await laravel<{ data?: Invitation; message?: string }>(`portal/invitations/${token}`, { headers: await visitorHeaders() });
  const signedIn = Boolean(await getTutorToken());

  return (
    <main className="mx-auto flex min-h-screen max-w-sm flex-col justify-center px-4 py-10">
      <Logo className="mb-8 h-8 w-auto self-center" />
      <div className="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-gray-900">
        {status !== 200 || !data.data ? (
          <>
            <h1 className="text-lg font-semibold">Invitación no disponible</h1>
            <p className="mt-2 text-theme-sm text-gray-500">{data.message ?? "Este enlace no es válido."}</p>
            <Link href="/portal/ingresar" className="mt-5 inline-block text-theme-sm font-semibold text-brand-600">
              Ir a ingresar →
            </Link>
          </>
        ) : (
          <Body token={token} invitation={data.data} signedIn={signedIn} />
        )}
      </div>
    </main>
  );
}

function Body({ token, invitation, signedIn }: { token: string; invitation: Invitation; signedIn: boolean }) {
  const pets = invitation.pets.length > 0 ? invitation.pets.join(", ") : "tus mascotas";

  return (
    <>
      <h1 className="text-lg font-semibold">¡Hola, {invitation.owner.first_name}!</h1>
      <p className="mt-1 mb-5 text-theme-sm text-gray-500">
        <b className="text-gray-700 dark:text-gray-200">{invitation.clinic.name}</b> te invita a ver el carné con QR de {pets}, sus vacunas y tus próximas citas.
      </p>

      {!invitation.account_exists ? (
        <>
          <ActivateForm token={token} name={invitation.owner.full_name} email={invitation.owner.email} />
          <p className="mt-4 text-center text-theme-xs text-gray-400">Solo lo haces una vez. Celular registrado: {invitation.phone_hint}</p>
        </>
      ) : signedIn ? (
        <LinkButton token={token} clinic={invitation.clinic.name} />
      ) : (
        <>
          <p className="mb-4 rounded-lg bg-brand-25 px-4 py-3 text-theme-sm text-brand-800 dark:bg-brand-500/10 dark:text-brand-300">
            Ya tienes una cuenta con el celular {invitation.phone_hint}. Ingresa y agregas esta veterinaria con un toque.
          </p>
          <Link
            href={`/portal/ingresar?next=${encodeURIComponent(`/portal/activar/${token}`)}`}
            className="block w-full rounded-xl bg-brand-500 px-4 py-3 text-center text-sm font-semibold text-white hover:bg-brand-600"
          >
            Ingresar
          </Link>
        </>
      )}
    </>
  );
}
