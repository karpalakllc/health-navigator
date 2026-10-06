import { NextResponse } from "next/server";
import { SESSION_COOKIE, sessionCookieOptions } from "@/lib/auth/session";
import { apiUrl } from "@/lib/config";
import { forwardedForHeaders } from "@/lib/api/client-ip";
import { guardJson } from "@/lib/auth/request-guard";
import { t } from "@/i18n/t";
import { readUpstream } from "@/lib/api/upstream";
import { deviceLabel } from "@/lib/device-label";

const TOKEN_MAX_AGE_SECONDS = 60 * 60 * 24 * 30;

type LoginPayload = {
  email?: string;
  password?: string;
};

export async function POST(request: Request) {
  const guarded = await guardJson<LoginPayload>(request);

  if (!guarded.ok) {
    return guarded.response;
  }

  const body = guarded.value;

  const upstream = await readUpstream(
    fetch(apiUrl("/auth/login"), {
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
        // Names the token in the account's device list („Chrome · macOS“).
        // Derived here from the browser's own request rather than taken from
        // the body, and only the coarse label leaves the web tier.
        device_name: deviceLabel(request.headers.get("user-agent")),
      }),
    }),
  );

  if (!upstream.ok) {
    return upstream.error;
  }

  const { response, payload } = upstream;

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
