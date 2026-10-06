import "server-only";
import { isIP } from "node:net";
import { headers } from "next/headers";
import { unstable_rethrow } from "next/navigation";

/**
 * Tells the API who the visitor is, and proves the claim comes from us.
 *
 * Every server-side render and every browser write reaches the API from this
 * server's address. Without a forwarded visitor address the API meters the
 * whole site as one client: one busy visitor exhausts the anonymous SSR budget
 * for everybody, and the sign-in limit becomes a site-wide cap.
 *
 * The API cannot recognise this tier by address (serverless/PaaS egress IPs
 * change), so it recognises a shared secret instead:
 *
 *   X-Web-Tier-Auth: <WEB_TIER_SECRET>   server-only env var, ≥ 32 chars
 *   X-Client-IP:     <visitor address>   honoured only alongside the secret
 *
 * `import "server-only"` makes any attempt to pull this module into a browser
 * bundle a build error, and WEB_TIER_SECRET has no NEXT_PUBLIC_ prefix so Next
 * never inlines it into client code either.
 *
 * Without the secret this falls back to the previous scheme — a single-entry
 * X-Forwarded-For — which the API only honours from TRUSTED_PROXIES. That keeps
 * a deployment that has not set the secret yet working as before.
 *
 * WHICH ADDRESS IS THE VISITOR
 *
 * Which header to believe is a deployment fact this code cannot infer, and a
 * wrong guess is exploitable: any header the edge does not overwrite is just
 * caller-supplied input, and believing it lets a client rotate the value and
 * mint itself a fresh rate-limit bucket per request. So it is configured:
 *
 *   CLIENT_IP_HEADER unset   → x-forwarded-for, rightmost entry. Each hop
 *                              appends, so the rightmost value is what the last
 *                              hop actually saw, and a caller-supplied entry
 *                              lands on the left. Correct behind any appending
 *                              edge (nginx `$proxy_add_x_forwarded_for`, Fly,
 *                              Render) and any overwriting one (Vercel). Behind
 *                              two hops (Cloudflare → nginx → Next) it yields
 *                              the inner proxy's view, i.e. a Cloudflare PoP:
 *                              visitors share buckets per PoP, which is safe
 *                              but coarse — name the edge's header instead.
 *   CLIENT_IP_HEADER=<name>  → that header (rightmost entry, if a list). Only
 *                              for a header your outermost edge overwrites,
 *                              e.g. cf-connecting-ip when the origin accepts
 *                              Cloudflare traffic only.
 *   CLIENT_IP_HEADER=none    → forward no address; the API meters this server.
 *
 * Why the default is the rightmost entry and not "none": `next start` only
 * fills x-forwarded-for when the request lacks one, so with Next listening on
 * the internet directly, with no proxy in front, the caller writes the whole
 * header and the rightmost entry is theirs. That topology is plain HTTP with
 * no TLS — not a production deployment — and it must set `none`. Every
 * topology with an edge (which TLS requires) is correct by default, whereas a
 * fail-closed default would put every visitor of an unconfigured deployment in
 * one bucket: the SSR budget shared site-wide, and one attacker able to lock
 * every visitor out of sign-in.
 */

const WEB_TIER_SECRET_MIN_LENGTH = 32;

/** The visitor's address as configured by CLIENT_IP_HEADER, if any. */
export function clientIpFrom(incoming: Headers): string | undefined {
  const configured = process.env.CLIENT_IP_HEADER?.trim().toLowerCase();

  if (configured === "none") {
    return undefined;
  }

  const value =
    (configured ? lastEntry(incoming.get(configured)) : undefined) ??
    lastEntry(incoming.get("x-forwarded-for"));

  return value === undefined ? undefined : normalizeIp(value);
}

/**
 * The headers every server-side API call carries. With the secret configured:
 * the secret, plus the visitor when known. Without it: the legacy
 * X-Forwarded-For, never the auth header.
 */
export function webTierHeaders(
  clientIp: string | undefined,
): Record<string, string> {
  const secret = webTierSecret();

  if (secret === undefined) {
    return clientIp ? { "X-Forwarded-For": clientIp } : {};
  }

  return clientIp
    ? { "X-Web-Tier-Auth": secret, "X-Client-IP": clientIp }
    : { "X-Web-Tier-Auth": secret };
}

/** For route handlers, which have the incoming request in hand. */
export function forwardedForHeaders(request: Request): Record<string, string> {
  return webTierHeaders(clientIpFrom(request.headers));
}

/**
 * For server-side rendering, where the incoming request is only reachable
 * through next/headers.
 *
 * `forwardVisitor: false` sends the secret without an address. Fetches that
 * opt into the shared data cache need that: their response is served to every
 * visitor anyway, a per-visitor header would fragment the cache key, and
 * reading request headers would turn a cached route dynamic.
 */
export async function webTierRequestHeaders({
  forwardVisitor = true,
}: { forwardVisitor?: boolean } = {}): Promise<Record<string, string>> {
  if (!forwardVisitor) {
    return webTierHeaders(undefined);
  }

  let incoming: Headers | undefined;

  try {
    incoming = await headers();
  } catch (error) {
    // Let Next's own control-flow errors through (dynamic-usage bailouts);
    // anything else means there is no request here, so there is no visitor.
    unstable_rethrow(error);
  }

  return webTierHeaders(incoming ? clientIpFrom(incoming) : undefined);
}

function webTierSecret(): string | undefined {
  const secret = process.env.WEB_TIER_SECRET?.trim();

  // The API ignores a shorter secret, so sending one would only expose it.
  return secret && secret.length >= WEB_TIER_SECRET_MIN_LENGTH
    ? secret
    : undefined;
}

function lastEntry(value: string | null): string | undefined {
  if (!value) {
    return undefined;
  }

  return value
    .split(",")
    .map((entry) => entry.trim())
    .filter(Boolean)
    .at(-1);
}

/**
 * Some proxies append the source port (`203.0.113.7:51234`, `[2001:db8::1]:443`).
 * Anything that is still not an address is dropped rather than forwarded — the
 * API would refuse it anyway.
 */
function normalizeIp(value: string): string | undefined {
  const bracketed = /^\[([^\]]+)\](?::\d+)?$/.exec(value);
  const candidate = bracketed
    ? bracketed[1]
    : /^\d{1,3}(?:\.\d{1,3}){3}:\d+$/.test(value)
      ? value.slice(0, value.lastIndexOf(":"))
      : value;

  return isIP(candidate) ? candidate : undefined;
}
