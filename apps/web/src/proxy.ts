import { NextResponse, type NextRequest } from "next/server";

/**
 * Per-request Content-Security-Policy with a script nonce.
 *
 * The CSP previously lived in next.config.ts headers and shipped
 * `script-src 'unsafe-inline'` in production, which removes most of what a CSP
 * buys you against injection. Nonces have to be minted per request, so they
 * cannot come from a static header — hence this file.
 *
 * Next 16 renamed the `middleware` convention to `proxy`; see
 * node_modules/next/dist/docs/01-app/03-api-reference/03-file-conventions/proxy.md.
 *
 * Nonces require dynamic rendering. That costs nothing here: the root layout is
 * already `force-dynamic` and every route reads cookies via getSessionToken().
 *
 * `style-src` deliberately keeps 'unsafe-inline'. A nonce does not cover the
 * HTML `style` *attribute*, which the guidance progress bar uses for its width,
 * and Next injects inline styles of its own. Tightening styles is a separate
 * change; the script-src nonce is where the security value is.
 */
export function proxy(request: NextRequest) {
  const nonce = Buffer.from(crypto.randomUUID()).toString("base64");
  const isDev = process.env.NODE_ENV !== "production";

  let apiOrigin = "";
  try {
    if (process.env.NEXT_PUBLIC_API_URL) {
      apiOrigin = new URL(process.env.NEXT_PUBLIC_API_URL).origin;
    }
  } catch {
    apiOrigin = "";
  }

  const csp = [
    "default-src 'self'",
    `script-src 'self' 'nonce-${nonce}' 'strict-dynamic'${isDev ? " 'unsafe-eval'" : ""}`,
    "style-src 'self' 'unsafe-inline'",
    ["img-src 'self'", "data:", "https://api.dicebear.com", apiOrigin].filter(Boolean).join(" "),
    "font-src 'self'",
    "frame-src https://www.openstreetmap.org",
    ["connect-src 'self'", apiOrigin].filter(Boolean).join(" "),
    "object-src 'none'",
    "base-uri 'self'",
    "form-action 'self'",
    "frame-ancestors 'none'",
  ].join("; ");

  const requestHeaders = new Headers(request.headers);
  requestHeaders.set("x-nonce", nonce);
  // Next reads the nonce back out of this header during SSR and applies it to
  // its own framework and RSC-payload script tags.
  requestHeaders.set("Content-Security-Policy", csp);

  const response = NextResponse.next({ request: { headers: requestHeaders } });
  response.headers.set("Content-Security-Policy", csp);

  return response;
}

export const config = {
  matcher: [
    {
      source: "/((?!api|_next/static|_next/image|favicon.ico).*)",
      missing: [
        { type: "header", key: "next-router-prefetch" },
        { type: "header", key: "purpose", value: "prefetch" },
      ],
    },
  ],
};
