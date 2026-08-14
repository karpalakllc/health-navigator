/**
 * Forwards the visitor's address to the API.
 *
 * Every write the browser makes goes browser → route handler → API. Without
 * this, the API sees one address for the entire platform — the web server's —
 * so `Limit::perMinute(…)->by($request->ip())` meters everybody into a single
 * bucket.
 *
 * Which header to believe depends on the edge, and getting it wrong is
 * exploitable in one direction:
 *
 *  - `x-real-ip` and `cf-connecting-ip` are single values written by the edge
 *    itself, so a client cannot contribute to them. Preferred.
 *  - `x-forwarded-for` is a chain each hop *appends* to. On an appending edge
 *    (nginx, Cloudflare) a client-supplied header ends up on the **left**, so
 *    taking the leftmost entry lets a caller rotate a value and reset its own
 *    bucket. The rightmost entry is the address the last hop actually saw, which
 *    is the one we can rely on with a single trusted edge — and on an
 *    overwriting edge (Vercel) the chain has one entry, so rightmost is the same
 *    value the leftmost would have been.
 *
 * Where no edge is present (local `next start`) there is nothing to forward and
 * the API falls back to the socket address, which is correct for that case.
 *
 * The API only honours this from addresses listed in TRUSTED_PROXIES, which must
 * therefore include the web tier as well as the edge — see infra/deploy.md.
 */
export function forwardedForHeaders(request: Request): Record<string, string> {
  const client = edgeAssignedIp(request) ?? nearestForwardedFor(request);

  return client ? { "X-Forwarded-For": client } : {};
}

/** Single-value headers written by the edge; a client cannot influence these. */
function edgeAssignedIp(request: Request): string | undefined {
  for (const header of ["cf-connecting-ip", "x-real-ip"]) {
    const value = request.headers.get(header)?.trim();

    if (value) {
      return value;
    }
  }

  return undefined;
}

/** The address the last hop saw — the rightmost entry of the chain. */
function nearestForwardedFor(request: Request): string | undefined {
  const chain = request.headers.get("x-forwarded-for");

  if (!chain) {
    return undefined;
  }

  const entries = chain
    .split(",")
    .map((entry) => entry.trim())
    .filter(Boolean);

  return entries.at(-1);
}
