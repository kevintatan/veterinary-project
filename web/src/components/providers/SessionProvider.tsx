"use client";

import type { Me } from "@/lib/types";
import { createContext, useContext } from "react";

const SessionContext = createContext<Me | null>(null);

/** Session loaded on the server (app/app/layout.tsx) and shared with client components. */
export function SessionProvider({ me, children }: { me: Me; children: React.ReactNode }) {
  return <SessionContext.Provider value={me}>{children}</SessionContext.Provider>;
}

export function useSession(): Me {
  const me = useContext(SessionContext);

  if (me === null) {
    throw new Error("useSession must be used inside <SessionProvider>.");
  }

  return me;
}

/** UI-level permission check (the API enforces it anyway). */
export function useCan(permission: string): boolean {
  return useSession().permissions.includes(permission);
}
