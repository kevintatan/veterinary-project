/**
 * Browser-side API client. Always goes through the Next.js BFF
 * (/api/backend/...), never to Laravel directly.
 */
export class ApiError extends Error {
  constructor(
    public readonly status: number,
    message: string,
    public readonly errors: Record<string, string[]> = {},
  ) {
    super(message);
  }

  /** First validation message of a field, for inline form errors. */
  field(name: string): string | undefined {
    return this.errors[name]?.[0];
  }
}

type Options = { method?: string; body?: unknown; signal?: AbortSignal };

export async function api<T>(path: string, options: Options = {}): Promise<T> {
  const isForm = options.body instanceof FormData;

  const response = await fetch(`/api/backend/${path.replace(/^\//, "")}`, {
    method: options.method ?? "GET",
    headers: {
      Accept: "application/json",
      ...(options.body !== undefined && !isForm ? { "Content-Type": "application/json" } : {}),
    },
    body: options.body === undefined ? undefined : isForm ? (options.body as FormData) : JSON.stringify(options.body),
    signal: options.signal,
  });

  if (response.status === 401) {
    // Deliberate full reload (not router.push): drops all cached data of the expired session.
    // eslint-disable-next-line @next/next/no-location-assign-relative-destination
    window.location.assign(`/login?next=${encodeURIComponent(window.location.pathname)}`);
    throw new ApiError(401, "Tu sesión expiró.");
  }

  if (response.status === 204) {
    return undefined as T;
  }

  const data = await response.json().catch(() => ({}));

  if (!response.ok) {
    throw new ApiError(response.status, data.message ?? "Ocurrió un error inesperado.", data.errors ?? {});
  }

  return data as T;
}
