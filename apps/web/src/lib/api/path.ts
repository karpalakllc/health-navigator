import { ApiRequestError } from "@/lib/api/errors";

/**
 * Building API paths from values that came from a URL or a request body.
 *
 * Slugs arrive from route params already decoded, so `/doctors/..%2Fme` hands
 * us `../me`. Interpolated raw, that became `/api/v1/doctors/../me` — which the
 * URL parser collapses to `/api/v1/me`, fetched server-side with the visitor's
 * bearer token. Every dynamic segment therefore goes through `pathSegment`.
 */

/**
 * Slugs as the API mints them (Str::slug) plus anything an admin could type that
 * is still a single, ordinary segment: letters in any script, digits, `-`, `_`.
 * No dots, slashes, percent signs or whitespace.
 */
const SLUG_PATTERN = /^[\p{L}\p{N}_-]{1,255}$/u;

export function isSlug(value: unknown): value is string {
  return typeof value === "string" && SLUG_PATTERN.test(value);
}

/**
 * Encode one path segment.
 *
 * encodeURIComponent takes care of `/`, `?`, `#` and `%` (so `%2e` arrives as a
 * harmless `%252e`), but leaves `.` alone — and a bare `.` or `..` is a dot
 * segment to every URL parser, so those are refused rather than encoded.
 */
export function pathSegment(value: string | number): string {
  const raw = String(value);

  if (raw === "" || raw === "." || raw === "..") {
    // A 404, not a 500: detail pages map ApiRequestError 404 to notFound(), and
    // `/doctors/..` is simply a page that does not exist.
    throw new ApiRequestError("Invalid API path segment", 404);
  }

  return encodeURIComponent(raw);
}
