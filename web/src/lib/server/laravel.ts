import "server-only";

import { headers } from "next/headers";

/**
 * Server-side client for the Laravel API (only the Next.js server talks to it).
 * API_URL is a server-only env var, e.g. http://localhost:8000/api/v1
 */
export function apiUrl(path: string): string {
  const base = process.env.API_URL;

  if (!base) {
    throw new Error("API_URL is not configured (web/.env.local).");
  }

  return `${base.replace(/\/$/, "")}/${path.replace(/^\//, "")}`;
}

/**
 * The visitor's IP for Laravel (trustProxies): rate limits must count each
 * person, not this server. Use on public calls made on behalf of a visitor.
 */
export async function visitorHeaders(): Promise<Record<string, string>> {
  const incoming = await headers();
  const ip = incoming.get("x-forwarded-for") ?? incoming.get("x-real-ip");
  return ip ? { "X-Forwarded-For": ip } : {};
}

export type LaravelResponse<T = unknown> = {
  status: number;
  data: T;
};

export async function laravel<T = unknown>(
  path: string,
  options: { method?: string; token?: string; body?: unknown; headers?: Record<string, string> } = {},
): Promise<LaravelResponse<T>> {
  const response = await fetch(apiUrl(path), {
    method: options.method ?? "GET",
    headers: {
      Accept: "application/json",
      ...(options.body !== undefined ? { "Content-Type": "application/json" } : {}),
      ...(options.token ? { Authorization: `Bearer ${options.token}` } : {}),
      ...options.headers,
    },
    body: options.body !== undefined ? JSON.stringify(options.body) : undefined,
    cache: "no-store",
  });

  const text = await response.text();
  const data = text ? (JSON.parse(text) as T) : (null as T);

  return { status: response.status, data };
}
