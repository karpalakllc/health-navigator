/**
 * Forwards the visitor's address to the API.
 *
 * Every write the browser makes goes browser → route handler → API. Without
 * this, the API sees one address for the entire platform — the web server's —
 * so `Limit::perMinute(…)->by($request->ip())` meters everybody into a single
 * bucket.
 *
 * Which header to believe is a deployment fact, not something this code can
 * infer, and guessing wrong is exploitable: any header the edge does not
 * overwrite is just caller-supplied input. `x-real-ip` and `cf-connecting-ip`
 * are only trustworthy behind an edge that writes them — behind one that does
 * not, a client can send either and pick its own rate-limit bucket.
 *
 * So the header is configured, not sniffed:
 *
 *   CLIENT_IP_HEADER unset  → x-forwarded-for, rightmost entry. The chain is
 *                             appended to by each hop, so the rightmost value is
 *                             the address the last hop actually saw; a
 *                             caller-supplied entry lands on the left and is
 *                             ignored. Correct on both appending edges (nginx,
 *                             Cloudflare) and overwriting ones (Vercel, where
 *                             the chain has a single entry).
 *   CLIENT_IP_HEADER=<name> → that header verbatim. Use only for a header your
 *                             edge is known to overwrite, e.g. cf-connecting-ip.
 *
 * Where no edge is present (local `next start`) there is nothing to forward and
 * the API falls back to the socket address, which is right for that case.
 *
 * The API only honours this from addresses in TRUSTED_PROXIES, which must
 * include the web tier as well as the edge — see infra/deploy.md.
 */
export function forwardedForHeaders(request: Request): Record<string, string> {
  const client = configuredHeader(request) ?? nearestForwardedFor(request);

  return client ? { "X-Forwarded-For": client } : {};
}

function configuredHeader(request: Request): string | undefined {
  const name = process.env.CLIENT_IP_HEADER?.trim().toLowerCase();

  if (!name) {
    return undefined;
  }

  // A configured header may still carry a chain (some edges set x-forwarded-for
  // by name); take its last entry for the same reason as below.
  return lastEntry(request.headers.get(name));
}

/** The address the last hop saw — the rightmost entry of the chain. */
function nearestForwardedFor(request: Request): string | undefined {
  return lastEntry(request.headers.get("x-forwarded-for"));
}

function lastEntry(value: string | null): string | undefined {
  if (!value) {
    return undefined;
  }

  const entries = value
    .split(",")
    .map((entry) => entry.trim())
    .filter(Boolean);

  return entries.at(-1);
}
