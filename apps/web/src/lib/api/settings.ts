import * as Sentry from "@sentry/nextjs";
import { cache } from "react";
import { API_LANGUAGE_HEADER } from "@/lib/api/client";
import {
  resolvePublicSettings,
  type PublicSettings,
  type PublicSettingsOutcome,
} from "@/lib/api/public-settings";
import type { ApiEnvelope } from "@/lib/api/types";
import { apiUrl } from "@/lib/config";

export {
  publicSettingsDefaults,
  type PublicSettings,
} from "@/lib/api/public-settings";

/**
 * How stale the public settings may be, in seconds. This is also how long an
 * admin toggle (maintenance, a module switch) takes to reach visitors.
 */
export const SETTINGS_REVALIDATE_SECONDS = 30;

/**
 * Reads /settings/public through Next's data cache.
 *
 * The endpoint is the same for everyone, so no bearer token is sent: a request
 * carrying `Authorization` is never cached by Next, and the token is not the
 * settings endpoint's business anyway.
 *
 * Why a fetch-level `revalidate` still works under the root layout's
 * `dynamic = "force-dynamic"` (which the per-request CSP nonce relies on): the
 * Next 16 docs describe force-dynamic as making every fetch `no-store`, but
 * patch-fetch (next/dist/server/lib/patch-fetch.js) only applies that to fetches
 * with *no* cache config of their own. An explicit `next.revalidate` is kept, so
 * the page still renders per request (fresh nonce) while this one fetch is served
 * from the data cache. Only 200 responses are written to that cache, so a failure
 * is never pinned for the window.
 *
 * This mirrors apiGet(path, { revalidate }) from lib/api/client.ts, which sets
 * the same `next: { revalidate }` init — it is not reused because it throws away
 * the status code, and the 503-means-maintenance decision needs it.
 *
 * Known gap: once an entry exists, Next serves it stale while revalidating in the
 * background. If the whole API goes down (503) after a good read, the last good
 * settings keep being served until a revalidation succeeds; the other, uncached
 * page fetches fail on their own in that window. A cold cache sees the 503 and
 * shows the maintenance page.
 */
export async function loadPublicSettings(
  revalidate: number = SETTINGS_REVALIDATE_SECONDS,
): Promise<PublicSettings> {
  let outcome: PublicSettingsOutcome;

  try {
    const response = await fetch(apiUrl("/settings/public"), {
      next: { revalidate },
      headers: { ...API_LANGUAGE_HEADER },
    });

    outcome = response.ok
      ? {
          kind: "ok",
          data: ((await response.json()) as ApiEnvelope<PublicSettings>).data,
        }
      : { kind: "http-error", status: response.status };
  } catch {
    outcome = { kind: "network-error" };
  }

  if (outcome.kind !== "ok") {
    // A silent fallback hides an outage behind a site that merely looks
    // smaller (modules off) — make sure somebody hears about it.
    Sentry.captureMessage(
      outcome.kind === "http-error"
        ? `Public settings unavailable (${outcome.status})`
        : "Public settings unavailable (network error)",
      "warning",
    );
  }

  return resolvePublicSettings(outcome);
}

/*
 * One memo cell per request: generateMetadata, the layout, the maintenance gate,
 * the header, the footer and any page-level call all share a single read.
 */
export const fetchPublicSettings = cache(() => loadPublicSettings());
