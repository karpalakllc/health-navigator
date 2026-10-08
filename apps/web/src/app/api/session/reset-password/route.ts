import { NextResponse } from "next/server";
import { apiUrl } from "@/lib/config";
import { forwardedForHeaders } from "@/lib/api/client-ip";
import { guardJson } from "@/lib/auth/request-guard";
import {
  decodeResetCookie,
  RESET_COOKIE,
  resetCookieOptions,
  type ResetCredentials,
} from "@/lib/auth/reset-token";
import { readUpstream } from "@/lib/api/upstream";
import { t } from "@/i18n/t";

type ResetPayload = {
  password?: string;
  password_confirmation?: string;
};

/** Reads the httpOnly reset cookie the browser sends — never trust the body. */
function credentialsFromRequest(request: Request): ResetCredentials | null {
  const header = request.headers.get("cookie") ?? "";

  for (const part of header.split(";")) {
    const trimmed = part.trim();
    const eq = trimmed.indexOf("=");

    if (eq === -1) {
      continue;
    }

    if (trimmed.slice(0, eq) !== RESET_COOKIE) {
      continue;
    }

    try {
      return decodeResetCookie(decodeURIComponent(trimmed.slice(eq + 1)));
    } catch {
      return null;
    }
  }

  return null;
}

/**
 * Forwards the password change to Laravel. Token and email come from the
 * httpOnly reset cookie only — the client body must not carry them.
 */
export async function POST(request: Request) {
  const guarded = await guardJson<ResetPayload>(request);

  if (!guarded.ok) {
    return guarded.response;
  }

  const credentials = credentialsFromRequest(request);

  if (!credentials) {
    return NextResponse.json(
      { message: t("auth.resetPasswordFailed") },
      { status: 422 },
    );
  }

  const body = guarded.value;

  const upstream = await readUpstream(
    fetch(apiUrl("/auth/reset-password"), {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        Accept: "application/json",
        "Accept-Language": "mk",
        ...forwardedForHeaders(request),
      },
      body: JSON.stringify({
        email: credentials.email,
        token: credentials.token,
        password: body.password,
        password_confirmation: body.password_confirmation,
      }),
    }),
  );

  if (!upstream.ok) {
    return upstream.error;
  }

  const { response, payload } = upstream;
  const res = NextResponse.json(payload, { status: response.status });

  // The token is spent; drop the copy the reset link left behind.
  if (response.ok) {
    res.cookies.set(RESET_COOKIE, "", resetCookieOptions(0));
  }

  return res;
}
