import { NextResponse } from "next/server";

/**
 * Sink for CSP violation reports.
 *
 * The policy is minted per request in src/proxy.ts, and a directive that is too
 * narrow does not fail loudly — it just stops something working, which is how
 * the missing Sentry ingest origin went unnoticed. Logging violations makes the
 * next such mistake visible.
 */
/**
 * The endpoint is unauthenticated and browsers can be made to post to it, so it
 * is sampled rather than logging every report — otherwise it is a free way to
 * fill the log. One distinct violation is as useful as ten thousand copies.
 */
const WINDOW_MS = 60_000;
const MAX_PER_WINDOW = 20;

let windowStartedAt = 0;
let loggedThisWindow = 0;

export async function POST(request: Request) {
  const now = Date.now();

  if (now - windowStartedAt > WINDOW_MS) {
    windowStartedAt = now;
    loggedThisWindow = 0;
  }

  if (loggedThisWindow < MAX_PER_WINDOW) {
    loggedThisWindow += 1;

    try {
      const report = await request.json();
      console.warn("[csp] violation", JSON.stringify(report).slice(0, 2000));
    } catch {
      // A malformed report is not worth failing over.
    }
  }

  // Always 204, whether or not it was logged — a browser has nothing useful to
  // do with an error here.
  return new NextResponse(null, { status: 204 });
}
