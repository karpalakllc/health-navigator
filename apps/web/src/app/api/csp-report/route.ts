import { NextResponse } from "next/server";

/**
 * Sink for CSP violation reports.
 *
 * The policy is minted per request in src/proxy.ts, and a directive that is too
 * narrow does not fail loudly — it just stops something working, which is how
 * the missing Sentry ingest origin went unnoticed. Logging violations makes the
 * next such mistake visible.
 */
export async function POST(request: Request) {
  try {
    const report = await request.json();
    console.warn("[csp] violation", JSON.stringify(report).slice(0, 2000));
  } catch {
    // A malformed report is not worth failing over.
  }

  return new NextResponse(null, { status: 204 });
}
