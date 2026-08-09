import { NextResponse } from "next/server";
import { SESSION_COOKIE, sessionCookieOptions } from "@/lib/auth/session";
import { apiUrl } from "@/lib/config";
import { t } from "@/i18n/t";

const TOKEN_MAX_AGE_SECONDS = 60 * 60 * 24 * 30;

type RegisterPayload = {
  name?: string;
  email?: string;
  password?: string;
  password_confirmation?: string;
  device_name?: string;
};

export async function POST(request: Request) {
  const body = (await request.json()) as RegisterPayload;

  const response = await fetch(apiUrl("/auth/register"), {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      Accept: "application/json",
      "Accept-Language": "mk",
    },
    body: JSON.stringify({
      name: body.name,
      email: body.email,
      password: body.password,
      password_confirmation: body.password_confirmation,
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
  res.cookies.set(SESSION_COOKIE, token, sessionCookieOptions(TOKEN_MAX_AGE_SECONDS));

  return res;
}
