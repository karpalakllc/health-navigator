import { NextResponse } from "next/server";
import { guardJson } from "@/lib/auth/request-guard";
import { isLikelyBot, relayToApi } from "@/lib/api/notifications-relay";
import { hasConsentHeader, statisticsHeaders } from "@/lib/consent/header";

/** Most review cards one report may carry (the API's ReviewViews::MAX_IDS). */
const MAX_IDS = 30;

/**
 * „Прикажана N пати“: the review cards that were on this visitor's screen
 * (ReviewViewTracker). Without the statistics-consent header nothing is
 * counted (204); crawlers are dropped too. Global Privacy Control / Do Not
 * Track alone no longer decide: an explicit yes in the banner overrides them.
 * The API counts each review at most once a day per network and never the
 * author's own views.
 */
export async function POST(request: Request) {
  if (!hasConsentHeader(request.headers)) {
    return new NextResponse(null, { status: 204 });
  }

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

  if (isLikelyBot(request)) {
    return NextResponse.json({ data: { counted: 0 } });
  }

  return relayToApi(request, "/reviews/views", {
    method: "POST",
    body: { ids },
    auth: "optional",
    headers: statisticsHeaders(),
  });
}
