import { NextResponse } from "next/server";
import { apiUrl } from "@/lib/config";
import { forwardedForHeaders } from "@/lib/api/client-ip";
import { guardJson } from "@/lib/auth/request-guard";
import { RESET_COOKIE, resetCookieOptions } from "@/lib/auth/reset-token";

type ResetPayload = {
  email?: string;
  token?: string;
  password?: string;
  password_confirmation?: string;
};

export async function POST(request: Request) {
  const guarded = await guardJson<ResetPayload>(request);

  if (!guarded.ok) {
    return guarded.response;
  }

  const body = guarded.value;

  const response = await fetch(apiUrl("/auth/reset-password"), {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      Accept: "application/json",
      "Accept-Language": "mk",
      ...forwardedForHeaders(request),
    },
    body: JSON.stringify({
      email: body.email,
      token: body.token,
      password: body.password,
      password_confirmation: body.password_confirmation,
    }),
  });

  const payload = await response.json();
  const res = NextResponse.json(payload, { status: response.status });

  // The token is spent; drop the copy the reset link left behind.
  if (response.ok) {
    res.cookies.set(RESET_COOKIE, "", resetCookieOptions(0));
  }

  return res;
}
