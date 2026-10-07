import { NextResponse } from "next/server";
import { apiUrl } from "@/lib/config";
import { forwardedForHeaders } from "@/lib/api/client-ip";
import { rejectCrossSite } from "@/lib/auth/request-guard";
import { readUpstream } from "@/lib/api/upstream";

/**
 * A fresh ALTCHA challenge for the forms' invisible widget
 * (GET /v1/altcha/challenge). Same-origin only; the visitor's address goes
 * along for the API's per-address limit. The challenge is relayed as the API
 * sends it (plain JSON, the shape the widget reads) and never cached.
 */
export async function GET(request: Request) {
  const refused = rejectCrossSite(request);

  if (refused) {
    return refused;
  }

  const upstream = await readUpstream(
    fetch(apiUrl("/altcha/challenge"), {
      headers: {
        Accept: "application/json",
        ...forwardedForHeaders(request),
      },
      cache: "no-store",
    }),
  );

  if (!upstream.ok) {
    return upstream.error;
  }

  return NextResponse.json(upstream.payload, {
    status: upstream.response.status,
    headers: { "Cache-Control": "no-store" },
  });
}
