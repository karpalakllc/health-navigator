import { NextResponse } from "next/server";
import { getSessionToken } from "@/lib/auth/session";
import { apiUrl } from "@/lib/config";
import { forwardedForHeaders } from "@/lib/api/client-ip";
import { rejectCrossSite } from "@/lib/auth/request-guard";
import { readUpstream } from "@/lib/api/upstream";

/**
 * „Is this username free?“ for the sign-up and account forms
 * (GET /usernames/availability). Same-origin only, so other sites cannot use
 * it as a free lookup; the visitor's address goes along for the API's
 * per-address limit, and the session token (when there is one) so that a
 * member's own current name reads as free.
 */
export async function GET(request: Request) {
  const refused = rejectCrossSite(request);

  if (refused) {
    return refused;
  }

  const username =
    new URL(request.url).searchParams.get("username")?.slice(0, 100) ?? "";
  const token = await getSessionToken();

  const ask = (bearer: string | null) =>
    readUpstream(
      fetch(
        apiUrl(
          `/usernames/availability?username=${encodeURIComponent(username)}`,
        ),
        {
          headers: {
            Accept: "application/json",
            "Accept-Language": "mk",
            ...(bearer ? { Authorization: `Bearer ${bearer}` } : {}),
            ...forwardedForHeaders(request),
          },
          cache: "no-store",
        },
      ),
    );

  let upstream = await ask(token ?? null);

  // An expired session cookie is refused even on optional-auth routes; the
  // question still has an answer without it.
  if (token && upstream.ok && upstream.response.status === 401) {
    upstream = await ask(null);
  }

  if (!upstream.ok) {
    return upstream.error;
  }

  const { response, payload } = upstream;

  return NextResponse.json(payload, {
    status: response.status,
    headers: { "Cache-Control": "no-store" },
  });
}
