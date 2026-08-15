import { NextResponse } from "next/server";
import { apiUrl } from "@/lib/config";
import { forwardedForHeaders } from "@/lib/api/client-ip";

/**
 * Re-sends the verification link. Like registration, the API's reply is
 * deliberately the same whatever the address turns out to be.
 */
export async function POST(request: Request) {
  const body = (await request.json()) as { email?: string };

  const response = await fetch(apiUrl("/auth/email/resend"), {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      Accept: "application/json",
      "Accept-Language": "mk",
      ...forwardedForHeaders(request),
    },
    body: JSON.stringify({ email: body.email }),
  });

  const payload = await response.json();

  return NextResponse.json(payload, { status: response.status });
}
