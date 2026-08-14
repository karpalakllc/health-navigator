import { NextResponse } from "next/server";
import { getSessionToken, SESSION_COOKIE } from "@/lib/auth/session";
import { apiUrl } from "@/lib/config";
import { forwardedForHeaders } from "@/lib/api/client-ip";
import { t } from "@/i18n/t";

export async function POST(request: Request) {
  const token = await getSessionToken();

  if (token) {
    await fetch(apiUrl("/auth/logout"), {
      method: "POST",
      headers: {
        Authorization: `Bearer ${token}`,
        Accept: "application/json",
        "Accept-Language": "mk",
        ...forwardedForHeaders(request),
      },
    });
  }

  const res = NextResponse.json({ data: { message: t("auth.loggedOut") } });
  res.cookies.set(SESSION_COOKIE, "", { path: "/", maxAge: 0 });

  return res;
}
