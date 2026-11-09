import "server-only";

import { laravel } from "@/lib/server/laravel";
import { getTutorToken } from "@/lib/server/tutor-session";
import { notFound, redirect } from "next/navigation";

/**
 * Reads the owner portal API from a Server Component, with the zoo_tutor
 * cookie. No session or expired token → login; 404 → not found page.
 */
export async function portalApi<T>(path: string): Promise<T> {
  const token = await getTutorToken();
  if (!token) redirect("/portal/ingresar");

  const { status, data } = await laravel<{ data: T }>(`portal/${path}`, { token });

  if (status === 401) redirect("/portal/ingresar?motivo=expirada");
  if (status === 404) notFound();
  if (status !== 200) throw new Error(`portal/${path}: ${status}`);

  return data.data;
}
