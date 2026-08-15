import { NextResponse } from "next/server";
import { SESSION_COOKIE, sessionCookieOptions } from "@/lib/auth/session";
import { apiUrl } from "@/lib/config";
import { forwardedForHeaders } from "@/lib/api/client-ip";
import { t } from "@/i18n/t";

const TOKEN_MAX_AGE_SECONDS = 60 * 60 * 24 * 30;

type LoginPayload = {
  email?: string;
  password?: string;
  device_name?: string;
};

export async function POST(request: Request) {
  const body = (await request.json()) as LoginPayload;

  const response = await fetch(apiUrl("/auth/login"), {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      Accept: "application/json",
      "Accept-Language": "mk",
      ...forwardedForHeaders(request),
    },
    body: JSON.stringify({
      email: body.email,
      password: body.password,
      device_name: body.device_name ?? "web",
    }),
  });

  const payload = await response.json();

  if (!response.ok) {
    return NextResponse.json(payload, { status: response.status });
  }

  const token = payload.data?.token as string | undefined;

  if (!token) {
    return NextResponse.json(
      { message: t("errors.missingToken") },
      { status: 502 },
    );
  }

  const res = NextResponse.json({ data: { user: payload.data.user } });
  res.cookies.set(
    SESSION_COOKIE,
    token,
    sessionCookieOptions(TOKEN_MAX_AGE_SECONDS),
  );

  return res;
}
