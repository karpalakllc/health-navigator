import { getSessionToken } from "@/lib/auth/session";
import { apiUrl } from "@/lib/config";
import type { ApiEnvelope, PaginatedEnvelope } from "@/lib/api/types";

export type ApiErrorBody = {
  message: string;
  errors?: Record<string, string[]>;
};

export class ApiRequestError extends Error {
  constructor(
    message: string,
    public readonly status: number,
    public readonly body?: ApiErrorBody,
  ) {
    super(message);
    this.name = "ApiRequestError";
  }
}

async function parseJson<T>(response: Response): Promise<T> {
  return (await response.json()) as T;
}

export async function apiFetch(
  path: string,
  init: RequestInit = {},
): Promise<Response> {
  const token = await getSessionToken();
  const headers = new Headers(init.headers);

  // Macedonian-only UI — see API_LANGUAGE_HEADER in lib/api/client.ts.
  if (!headers.has("Accept-Language")) {
    headers.set("Accept-Language", "mk");
  }

  if (token) {
    headers.set("Authorization", `Bearer ${token}`);
  }

  if (init.body && !headers.has("Content-Type")) {
    headers.set("Content-Type", "application/json");
  }

  return fetch(apiUrl(path), {
    ...init,
    headers,
    cache: "no-store",
  });
}

export async function apiGetServer<T>(path: string): Promise<T> {
  const response = await apiFetch(path);

  if (!response.ok) {
    const body = await parseJson<ApiErrorBody>(response).catch(() => undefined);
    throw new ApiRequestError(
      body?.message ?? `API request failed (${response.status})`,
      response.status,
      body,
    );
  }

  const envelope = await parseJson<ApiEnvelope<T>>(response);
  return envelope.data;
}

export async function apiGetPaginatedServer<T>(
  path: string,
): Promise<PaginatedEnvelope<T>> {
  const response = await apiFetch(path);

  if (!response.ok) {
    const body = await parseJson<ApiErrorBody>(response).catch(() => undefined);
    throw new ApiRequestError(
      body?.message ?? `API request failed (${response.status})`,
      response.status,
      body,
    );
  }

  return parseJson<PaginatedEnvelope<T>>(response);
}
