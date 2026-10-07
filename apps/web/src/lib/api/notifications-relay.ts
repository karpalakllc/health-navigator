import { NextResponse } from "next/server";
import { getSessionToken } from "@/lib/auth/session";
import { apiUrl } from "@/lib/config";
import { forwardedForHeaders } from "@/lib/api/client-ip";
import { readUpstream } from "@/lib/api/upstream";
import { t } from "@/i18n/t";

/**
 * W8-B route handlers relay to the API with the session's token (when there
 * is one) and the visitor's address for its per-client limits. The caller
 * has already done the same-origin and body checks.
 */
export async function relayToApi(
  request: Request,
  path: string,
  {
    method,
    body,
    auth,
    headers: extraHeaders,
  }: {
    method: "GET" | "POST" | "PUT" | "DELETE";
    /** Extra request headers (e.g. the statistics-consent header). */
    headers?: Record<string, string>;
    body?: unknown;
    /** "required": 401 without a session; "optional": sent when present; "none": never. */
    auth: "required" | "optional" | "none";
  },
): Promise<Response> {
  const token = auth === "none" ? null : await getSessionToken();

  if (auth === "required" && !token) {
    return NextResponse.json(
      { message: t("errors.unauthenticated") },
      { status: 401 },
    );
  }

  const upstream = await readUpstream(
    fetch(apiUrl(path), {
      method,
      headers: {
        ...(token ? { Authorization: `Bearer ${token}` } : {}),
        Accept: "application/json",
        "Accept-Language": "mk",
        ...(body !== undefined ? { "Content-Type": "application/json" } : {}),
        ...forwardedForHeaders(request),
        ...extraHeaders,
      },
      ...(body !== undefined ? { body: JSON.stringify(body) } : {}),
    }),
  );

  if (!upstream.ok) {
    return upstream.error;
  }

  return NextResponse.json(upstream.payload, {
    status: upstream.response.status,
  });
}

/** Crawlers and link previewers never count as a view (ReviewViews). */
const BOT_UA =
  /bot|crawl|spider|slurp|preview|headless|lighthouse|facebookexternalhit|whatsapp|python|curl|wget|httpclient|okhttp/i;

export function isLikelyBot(request: Request): boolean {
  const agent = request.headers.get("user-agent") ?? "";

  return agent.trim() === "" || BOT_UA.test(agent);
}
