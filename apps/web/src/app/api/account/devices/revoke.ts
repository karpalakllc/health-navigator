import { NextResponse } from "next/server";
import { getSessionToken } from "@/lib/auth/session";
import { apiUrl } from "@/lib/config";
import { forwardedForHeaders } from "@/lib/api/client-ip";
import { rejectCrossSite } from "@/lib/auth/request-guard";
import { readUpstream } from "@/lib/api/upstream";
import { t } from "@/i18n/t";

/**
 * Relays a device sign-out (DELETE /me/tokens or /me/tokens/{id}) with the
 * session's own token. No body: the same-origin check is what stops another
 * site from signing the member's devices out.
 */
export async function revokeTokens(
  request: Request,
  path: string,
): Promise<Response> {
  const crossSite = rejectCrossSite(request);

  if (crossSite) {
    return crossSite;
  }

  const token = await getSessionToken();

  if (!token) {
    return NextResponse.json(
      { message: t("errors.unauthenticated") },
      { status: 401 },
    );
  }

  const upstream = await readUpstream(
    fetch(apiUrl(path), {
      method: "DELETE",
      headers: {
        Authorization: `Bearer ${token}`,
        Accept: "application/json",
        "Accept-Language": "mk",
        ...forwardedForHeaders(request),
      },
    }),
  );

  if (!upstream.ok) {
    return upstream.error;
  }

  return NextResponse.json(upstream.payload, {
    status: upstream.response.status,
  });
}
