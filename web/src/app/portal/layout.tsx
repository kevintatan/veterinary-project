import type { Metadata } from "next";

// Personal data: never indexed.
export const metadata: Metadata = {
  title: { default: "Mis mascotas", template: "%s · Zooetia" },
  robots: { index: false, follow: false },
};

/** Owner portal (Fase 4e): mobile first, no clinic sidebar. */
export default function PortalLayout({ children }: { children: React.ReactNode }) {
  return <div className="min-h-screen bg-gray-50 text-gray-900 dark:bg-gray-950 dark:text-white">{children}</div>;
}
