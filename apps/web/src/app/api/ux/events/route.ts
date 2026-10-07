import { NextResponse } from "next/server";
import { forwardedForHeaders } from "@/lib/api/client-ip";
import { apiUrl } from "@/lib/config";
import { readLimited, rejectCrossSite } from "@/lib/auth/request-guard";
import { privacySignalHeader } from "@/lib/ux/privacy-signals";
import { parseUxBatch } from "@/lib/ux/validate";

/**
 * Relay for the anonymous UX tracker (docs/ux-heatmaps.md).
 *
 * Same-origin only (the shared request guard), JSON only, small, and rebuilt
 * field by field before it goes on, so nothing but bounded counters reaches
 * the API. The visitor's address is forwarded for the API's rate limit, which
 * keeps only an expiring hashed key; no cookie or session is read or sent.
 *
 * The browser has nothing useful to do with an error here, so every outcome
 * after the origin check is 204 — dropped batches included.
 */
const MAX_BATCH_BYTES = 16 * 1024;

/** Per-process ceiling, so a flood cannot turn into a flood on the API. */
const WINDOW_MS = 60_000;
const MAX_PER_WINDOW = 1_200;

let windowStartedAt = 0;
let relayedThisWindow = 0;

function noContent() {
  return new NextResponse(null, { status: 204 });
}

export async function POST(request: Request) {
  const crossSite = rejectCrossSite(request);

  if (crossSite) {
    return crossSite;
  }

  // Global Privacy Control / Do Not Track: the tracker already sends nothing,
  // this is the server-side backstop.
  if (privacySignalHeader(request.headers)) {
    return noContent();
  }

  const type = (request.headers.get("content-type") ?? "")
    .split(";")[0]
    .trim()
    .toLowerCase();

  if (type !== "application/json") {
    return noContent();
  }

  const bytes = await readLimited(request, MAX_BATCH_BYTES);

  if (!bytes || bytes.byteLength === 0) {
    return noContent();
  }

  let batch;

  try {
    batch = parseUxBatch(
      JSON.parse(new TextDecoder("utf-8", { fatal: true }).decode(bytes)),
    );
  } catch {
    return noContent();
  }

  if (!batch) {
    return noContent();
  }

  const now = Date.now();

  if (now - windowStartedAt > WINDOW_MS) {
    windowStartedAt = now;
    relayedThisWindow = 0;
  }

  if (relayedThisWindow >= MAX_PER_WINDOW) {
    return noContent();
  }

  relayedThisWindow += 1;

  try {
    await fetch(apiUrl("/ux/events"), {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        Accept: "application/json",
        ...forwardedForHeaders(request),
      },
      body: JSON.stringify(batch),
      cache: "no-store",
    });
  } catch {
    // Statistics are best effort.
  }

  return noContent();
}
