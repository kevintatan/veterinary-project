import { laravel } from "@/lib/server/laravel";
import { getSessionToken } from "@/lib/server/session";
import type { Metadata } from "next";
import Link from "next/link";
import AcceptInvitation from "./AcceptInvitation";

export const metadata: Metadata = { title: "Invitación" };

type InvitationInfo = { clinic: { name: string }; email: string; role: string; expires_at: string; account_exists: boolean };

/** Public page the invitation email links to. */
export default async function Page({ params }: { params: Promise<{ token: string }> }) {
  const { token } = await params;
  const valid = /^[A-Za-z0-9]{48}$/.test(token);
  const { status, data } = valid ? await laravel<{ data?: InvitationInfo; message?: string }>(`invitations/${token}`) : { status: 404, data: {} as { data?: InvitationInfo; message?: string } };

  if (status !== 200 || !data.data) {
    return (
      <div>
        <h1 className="mb-2 text-title-sm font-semibold text-gray-800 dark:text-white/90">Invitación no disponible</h1>
        <p className="mb-6 text-theme-sm text-gray-500 dark:text-gray-400">
          {data.message ?? "El enlace no es válido, ya se usó o venció. Pide a la clínica que te envíe una nueva invitación."}
        </p>
        <Link href="/login" className="text-theme-sm font-medium text-brand-600 hover:underline">
          Ir a iniciar sesión
        </Link>
      </div>
    );
  }

  const info = data.data;
  const signedIn = !!(await getSessionToken());

  return (
    <div>
      <p className="mb-2 text-theme-sm font-medium text-brand-600 dark:text-brand-400">Te invitaron a Zooetia</p>
      <h1 className="mb-2 text-title-sm font-semibold text-gray-800 dark:text-white/90">{info.clinic.name}</h1>
      <p className="mb-8 text-theme-sm text-gray-500 dark:text-gray-400">
        Entrarás como <span className="font-medium text-gray-700 dark:text-gray-300">{info.role}</span> con el correo{" "}
        <span className="font-medium text-gray-700 dark:text-gray-300">{info.email}</span>.
      </p>
      <AcceptInvitation token={token} email={info.email} accountExists={info.account_exists} signedIn={signedIn} />
    </div>
  );
}
