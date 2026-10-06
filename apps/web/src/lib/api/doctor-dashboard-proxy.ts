import { NextResponse } from "next/server";
import { getSessionToken } from "@/lib/auth/session";
import { apiUrl } from "@/lib/config";
import { forwardedForHeaders } from "@/lib/api/client-ip";
import { readUpstream } from "@/lib/api/upstream";
import { t } from "@/i18n/t";

/**
 * The „Мој профил“ route handlers (src/app/api/doctor-dashboard/**) relay one
 * call each to the API with the session token, which never reaches the
 * browser. Each handler decides what it forwards; this only does the relay.
 */
export async function relayToApi(
  request: Request,
  {
    method,
    path,
    body,
  }: {
    method: "POST" | "PUT" | "PATCH" | "DELETE";
    path: string;
    body?: Record<string, unknown>;
  },
): Promise<NextResponse> {
  const token = await getSessionToken();

  if (!token) {
    return NextResponse.json(
      { message: t("errors.unauthenticated") },
      { status: 401 },
    );
  }

  const upstream = await readUpstream(
    fetch(apiUrl(path), {
      method,
      headers: {
        Authorization: `Bearer ${token}`,
        Accept: "application/json",
        "Accept-Language": "mk",
        ...(body ? { "Content-Type": "application/json" } : {}),
        ...forwardedForHeaders(request),
      },
      body: body ? JSON.stringify(body) : undefined,
    }),
  );

  if (!upstream.ok) {
    return upstream.error;
  }

  return NextResponse.json(upstream.payload, {
    status: upstream.response.status,
  });
}

/** Keep only the listed keys of a request body. */
export function pick(
  value: Record<string, unknown>,
  keys: readonly string[],
): Record<string, unknown> {
  return Object.fromEntries(
    keys.filter((key) => key in value).map((key) => [key, value[key]]),
  );
}

export function isPositiveId(value: string): boolean {
  return /^[1-9][0-9]{0,18}$/.test(value);
}
