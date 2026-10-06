import { NextResponse } from "next/server";
import { getSessionToken, SESSION_COOKIE } from "@/lib/auth/session";
import { apiUrl } from "@/lib/config";
import { forwardedForHeaders } from "@/lib/api/client-ip";
import { rejectCrossSite } from "@/lib/auth/request-guard";
import { t } from "@/i18n/t";

export async function POST(request: Request) {
  // No body to type-check, but another site must not be able to sign people out.
  const crossSite = rejectCrossSite(request);

  if (crossSite) {
    return crossSite;
  }

  const token = await getSessionToken();

  if (token) {
    // Revoking the token upstream is best-effort: if the API is unreachable the
    // browser still has to end up signed out, so the cookie is cleared regardless.
    await fetch(apiUrl("/auth/logout"), {
      method: "POST",
      headers: {
        Authorization: `Bearer ${token}`,
        Accept: "application/json",
        "Accept-Language": "mk",
        ...forwardedForHeaders(request),
      },
    }).catch(() => undefined);
  }

  const res = NextResponse.json({ data: { message: t("auth.loggedOut") } });
  res.cookies.set(SESSION_COOKIE, "", { path: "/", maxAge: 0 });

  return res;
}
