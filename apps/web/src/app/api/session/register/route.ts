import { NextResponse } from "next/server";
import { apiUrl } from "@/lib/config";
import { forwardedForHeaders } from "@/lib/api/client-ip";

type RegisterPayload = {
  name?: string;
  email?: string;
  password?: string;
  password_confirmation?: string;
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
  const body = (await request.json()) as RegisterPayload;

  const response = await fetch(apiUrl("/auth/register"), {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      Accept: "application/json",
      "Accept-Language": "mk",
      ...forwardedForHeaders(request),
    },
    body: JSON.stringify({
      name: body.name,
      email: body.email,
      password: body.password,
      password_confirmation: body.password_confirmation,
    }),
  });

  const payload = await response.json();

  return NextResponse.json(payload, { status: response.status });
}
