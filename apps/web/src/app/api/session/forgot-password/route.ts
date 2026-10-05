import { NextResponse } from "next/server";
import { apiUrl } from "@/lib/config";
import { forwardedForHeaders } from "@/lib/api/client-ip";
import { guardJson } from "@/lib/auth/request-guard";

export async function POST(request: Request) {
  const guarded = await guardJson<{ email?: string }>(request);

  if (!guarded.ok) {
    return guarded.response;
  }

  const body = guarded.value;

  const response = await fetch(apiUrl("/auth/forgot-password"), {
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
