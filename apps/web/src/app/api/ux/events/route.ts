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
 * keeps only an expiring keyed hash of the network; no cookie or session is
 * read or sent.
 *
 * The browser has nothing useful to do with an error here, so every outcome
 * after the origin check is 204 — dropped batches included.
 */
const MAX_BATCH_BYTES = 16 * 1024;

/**
 * A hung API must not pile up relay requests. There is deliberately no
 * relay-wide ceiling: one busy client would use it up for every visitor. The
 * API limits per visitor network (the `api-ux-events` limiter).
 */
const UPSTREAM_TIMEOUT_MS = 3_000;

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
      signal: AbortSignal.timeout(UPSTREAM_TIMEOUT_MS),
    });
  } catch {
    // Statistics are best effort.
  }

  return noContent();
}
