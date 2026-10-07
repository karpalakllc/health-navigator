import { NextResponse } from "next/server";
import { forwardedForHeaders } from "@/lib/api/client-ip";
import { apiUrl } from "@/lib/config";
import { readLimited, rejectCrossSite } from "@/lib/auth/request-guard";
import {
  feedbackApiBody,
  feedbackApiPath,
  parseFeedbackMessage,
} from "@/lib/feedback";
import { hasConsentHeader, statisticsHeaders } from "@/lib/consent/header";

/**
 * Relay for „Дали ви помогна?“ votes and step counters (docs/urgent-care.md
 * § Feedback). Same-origin only, JSON only, tiny, and rebuilt field by field
 * from closed vocabularies before it goes on, so no free text reaches the
 * API. The visitor's address is forwarded for the API's rate limit only (an
 * expiring keyed hash of the network); no cookie or session is read or sent.
 *
 * Step counters are statistics: without the consent header they are dropped. A vote is something the visitor chose to
 * send, so it goes through.
 *
 * Every outcome after the origin check is 204: the page has nothing useful
 * to do with an error.
 */
const MAX_BYTES = 2 * 1024;
const UPSTREAM_TIMEOUT_MS = 3_000;

function noContent() {
  return new NextResponse(null, { status: 204 });
}

export async function POST(request: Request) {
  const crossSite = rejectCrossSite(request);

  if (crossSite) {
    return crossSite;
  }

  const type = (request.headers.get("content-type") ?? "")
    .split(";")[0]
    .trim()
    .toLowerCase();

  if (type !== "application/json") {
    return noContent();
  }

  const bytes = await readLimited(request, MAX_BYTES);

  if (!bytes || bytes.byteLength === 0) {
    return noContent();
  }

  let message;

  try {
    message = parseFeedbackMessage(
      JSON.parse(new TextDecoder("utf-8", { fatal: true }).decode(bytes)),
    );
  } catch {
    return noContent();
  }

  if (!message) {
    return noContent();
  }

  if (message.kind === "step" && !hasConsentHeader(request.headers)) {
    return noContent();
  }

  try {
    await fetch(apiUrl(feedbackApiPath(message)), {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        Accept: "application/json",
        ...forwardedForHeaders(request),
        ...(message.kind === "step" ? statisticsHeaders() : {}),
      },
      body: JSON.stringify(feedbackApiBody(message)),
      cache: "no-store",
      signal: AbortSignal.timeout(UPSTREAM_TIMEOUT_MS),
    });
  } catch {
    // Best effort.
  }

  return noContent();
}
