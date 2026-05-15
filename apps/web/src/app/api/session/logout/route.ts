import { NextResponse } from "next/server";
import { getSessionToken, SESSION_COOKIE } from "@/lib/auth/session";
import { apiUrl } from "@/lib/config";

export async function POST() {
  const token = await getSessionToken();

  if (token) {
    await fetch(apiUrl("/auth/logout"), {
      method: "POST",
      headers: {
        Authorization: `Bearer ${token}`,
        Accept: "application/json",
      },
    });
  }

  const res = NextResponse.json({ data: { message: "Logged out." } });
  res.cookies.set(SESSION_COOKIE, "", { path: "/", maxAge: 0 });

  return res;
}
