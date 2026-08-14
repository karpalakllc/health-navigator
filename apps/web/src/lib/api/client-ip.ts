/**
 * Forwards the visitor's address to the API.
 *
 * Every write the browser makes goes browser → route handler → API. Without
 * this, the API sees one address for the entire platform — the web server's —
 * so `Limit::perMinute(5)->by($request->ip())` on sign-in meters everybody into
 * a single bucket. Five sign-ins a minute, site-wide, successes included.
 *
 * The incoming `x-forwarded-for` is written by the hosting edge, which
 * overwrites rather than appends, so its leftmost entry is the real client.
 * Where no edge is present (local `next start`) there is nothing to forward and
 * the API falls back to the socket address, which is correct for that case.
 *
 * The API only honours this header from addresses listed in TRUSTED_PROXIES,
 * which must therefore include the web tier as well as the edge — see
 * infra/deploy.md.
 */
export function forwardedForHeaders(request: Request): Record<string, string> {
  const chain = request.headers.get("x-forwarded-for");
  const client = chain?.split(",")[0]?.trim() || request.headers.get("x-real-ip")?.trim();

  return client ? { "X-Forwarded-For": client } : {};
}
