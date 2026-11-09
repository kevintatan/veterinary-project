import "server-only";

import type { Me } from "@/lib/types";
import { redirect } from "next/navigation";
import { laravel } from "./laravel";
import { getSessionToken } from "./session";

/**
 * Current session, loaded on the server for the app shell.
 * Any rejection (expired token, member disabled, clinic suspended) goes
 * through /api/auth/expired, which can clear the cookie (pages cannot).
 */
export async function getMe(): Promise<Me> {
  const token = await getSessionToken();

  if (!token) {
    redirect("/login");
  }

  const { status, data } = await laravel<{ data: Me; message?: string }>("me", { token });

  if (status !== 200) {
    redirect(`/api/auth/expired?reason=${status === 403 ? "forbidden" : "expired"}`);
  }

  return data.data;
}
