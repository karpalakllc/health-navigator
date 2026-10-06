import { isHttpsOrigin } from "./site-url";

/*
 * Static response headers for every route (next.config.ts). Relative import
 * above, not "@/…": next.config.ts loads this before the path alias exists.
 *
 * Content-Security-Policy is NOT set here. It is minted per request in
 * src/proxy.ts so each response can carry a fresh script nonce; a static header
 * would shadow that and force `unsafe-inline` back in.
 */

type Header = { key: string; value: string };

/**
 * One year, subdomains included. No `preload`: that is a one-way submission to
 * the browsers' built-in lists and is a decision for whoever owns the domain.
 */
export const HSTS_VALUE = "max-age=31536000; includeSubDomains";

/**
 * @param siteUrl NEXT_PUBLIC_SITE_URL. HSTS is sent only when it is https: a
 *   plain-http origin (local dev, the E2E stack) is not serving TLS, so there
 *   is nothing to pin.
 */
export function securityHeaders(siteUrl: string | undefined): Header[] {
  const headers: Header[] = [
    { key: "X-Frame-Options", value: "DENY" },
    { key: "X-Content-Type-Options", value: "nosniff" },
    { key: "Referrer-Policy", value: "strict-origin-when-cross-origin" },
    {
      key: "Permissions-Policy",
      value: "camera=(), microphone=(), geolocation=()",
    },
  ];

  if (isHttpsOrigin(siteUrl)) {
    headers.push({ key: "Strict-Transport-Security", value: HSTS_VALUE });
  }

  return headers;
}
