import { NextResponse } from "next/server";
import { guardJson } from "@/lib/auth/request-guard";
import { isLikelyBot, relayToApi } from "@/lib/api/notifications-relay";
import { privacySignalHeader } from "@/lib/ux/privacy-signals";

/** Most review cards one report may carry (the API's ReviewViews::MAX_IDS). */
const MAX_IDS = 30;

/**
 * „Прикажана N пати“: the review cards that were on this visitor's screen
 * (ReviewViewTracker). Crawlers and privacy signals are dropped here; the API counts each review
 * at most once a day per network and never the author's own views.
 */
export async function POST(request: Request) {
  const guarded = await guardJson<{ ids?: unknown }>(request, 4 * 1024);

  if (!guarded.ok) {
    return guarded.response;
  }

  const ids = guarded.value.ids;

  if (
    !Array.isArray(ids) ||
    ids.length === 0 ||
    ids.length > MAX_IDS ||
    !ids.every((id) => Number.isSafeInteger(id) && id > 0)
  ) {
    return NextResponse.json({ message: "Invalid ids." }, { status: 422 });
  }

  // Crawlers, and (as for the UX statistics) Sec-GPC / DNT: not counted.
  if (isLikelyBot(request) || privacySignalHeader(request.headers)) {
    return NextResponse.json({ data: { counted: 0 } });
  }

  return relayToApi(request, "/reviews/views", {
    method: "POST",
    body: { ids },
    auth: "optional",
  });
}
