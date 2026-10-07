import { NextResponse } from "next/server";
import { apiUrl } from "@/lib/config";
import { forwardedForHeaders } from "@/lib/api/client-ip";
import { guardJson } from "@/lib/auth/request-guard";
import { readUpstream } from "@/lib/api/upstream";

type RegisterPayload = {
  name?: string;
  username?: string;
  email?: string;
  password?: string;
  password_confirmation?: string;
  accept_terms?: boolean;
  /** ALTCHA payload from the form's invisible widget; the API checks it. */
  altcha?: unknown;
};

/**
 * Registration no longer establishes a session.
 *
 * The API answers 202 with the same message whether or not the address is
 * already registered — that is what stops signup being usable to discover who
 * has an account here. There is no token to store until the address has been
 * verified, so this handler simply relays the API's response.
 */
export async function POST(request: Request) {
  const guarded = await guardJson<RegisterPayload>(request);

  if (!guarded.ok) {
    return guarded.response;
  }

  const body = guarded.value;

  const upstream = await readUpstream(
    fetch(apiUrl("/auth/register"), {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        Accept: "application/json",
        "Accept-Language": "mk",
        ...forwardedForHeaders(request),
      },
      body: JSON.stringify({
        name: body.name,
        username: body.username,
        email: body.email,
        password: body.password,
        password_confirmation: body.password_confirmation,
        accept_terms: body.accept_terms,
        altcha: typeof body.altcha === "string" ? body.altcha : null,
      }),
    }),
  );

  if (!upstream.ok) {
    return upstream.error;
  }

  const { response, payload } = upstream;

  return NextResponse.json(payload, { status: response.status });
}
