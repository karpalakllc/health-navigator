import { NextResponse } from "next/server";
import { getSessionToken, SESSION_COOKIE } from "@/lib/auth/session";
import { apiUrl } from "@/lib/config";
import { t } from "@/i18n/t";

export async function POST() {
  const token = await getSessionToken();

  if (token) {
    await fetch(apiUrl("/auth/logout"), {
      method: "POST",
      headers: {
        Authorization: `Bearer ${token}`,
        Accept: "application/json",
      "Accept-Language": "mk",
      },
    });
  }

  const res = NextResponse.json({ data: { message: t("errors.loggedOut") } });
  res.cookies.set(SESSION_COOKIE, "", { path: "/", maxAge: 0 });

  return res;
}
