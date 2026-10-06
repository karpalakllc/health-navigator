import { NextResponse } from "next/server";
import { getSessionToken, SESSION_COOKIE } from "@/lib/auth/session";
import { apiUrl } from "@/lib/config";
import { forwardedForHeaders } from "@/lib/api/client-ip";
import { guardJson } from "@/lib/auth/request-guard";
import { readUpstream } from "@/lib/api/upstream";
import { t } from "@/i18n/t";

type DeletePayload = {
  password?: unknown;
};

/**
 * Deletes the signed-in member's account (DELETE /me), confirmed by their
 * password. On success the session cookie is cleared too: the API has already
 * revoked every token.
 */
export async function POST(request: Request) {
  const token = await getSessionToken();

  if (!token) {
    return NextResponse.json(
      { message: t("errors.unauthenticated") },
      { status: 401 },
    );
  }

  const guarded = await guardJson<DeletePayload>(request);

  if (!guarded.ok) {
    return guarded.response;
  }

  const password = guarded.value.password;

  const upstream = await readUpstream(
    fetch(apiUrl("/me"), {
      method: "DELETE",
      headers: {
        Authorization: `Bearer ${token}`,
        "Content-Type": "application/json",
        Accept: "application/json",
        "Accept-Language": "mk",
        ...forwardedForHeaders(request),
      },
      // Only the password is forwarded, whatever else the body carried.
      body: JSON.stringify({
        password: typeof password === "string" ? password : "",
      }),
    }),
  );

  if (!upstream.ok) {
    return upstream.error;
  }

  const { response, payload } = upstream;
  const res = NextResponse.json(payload, { status: response.status });

  if (response.ok) {
    res.cookies.set(SESSION_COOKIE, "", { path: "/", maxAge: 0 });
  }

  return res;
}
