import { SessionProvider } from "@/components/providers/SessionProvider";
import AdminShell from "@/layout/AdminShell";
import { getMe } from "@/lib/server/me";

/**
 * Dashboard shell. The session (/me) is loaded on the server on every
 * navigation, so a disabled member or suspended clinic is cut off immediately.
 */
export default async function AppLayout({ children }: { children: React.ReactNode }) {
  const me = await getMe();

  return (
    <SessionProvider me={me}>
      <AdminShell>{children}</AdminShell>
    </SessionProvider>
  );
}
