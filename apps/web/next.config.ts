import type { NextConfig } from "next";
import { withSentryConfig } from "@sentry/nextjs";

function contentSecurityPolicy(): string {
  let apiOrigin = "";

  try {
    if (process.env.NEXT_PUBLIC_API_URL) {
      apiOrigin = new URL(process.env.NEXT_PUBLIC_API_URL).origin;
    }
  } catch {
    apiOrigin = "";
  }

  const connectSrc = ["'self'", apiOrigin].filter(Boolean).join(" ");
  const imgSrc = ["'self'", "data:", "https://api.dicebear.com", apiOrigin].filter(Boolean).join(" ");
  const isDev = process.env.NODE_ENV !== "production";
  const scriptSrc = ["'self'", "'unsafe-inline'", ...(isDev ? ["'unsafe-eval'"] : [])].join(" ");

  return [
    "default-src 'self'",
    `script-src ${scriptSrc}`,
    "style-src 'self' 'unsafe-inline'",
    `img-src ${imgSrc}`,
    "font-src 'self'",
    "frame-src https://www.openstreetmap.org",
    `connect-src ${connectSrc}`,
    "base-uri 'self'",
    "form-action 'self'",
    "frame-ancestors 'none'",
  ].join("; ");
}

const securityHeaders = [
  { key: "X-Frame-Options", value: "DENY" },
  { key: "X-Content-Type-Options", value: "nosniff" },
  { key: "Referrer-Policy", value: "strict-origin-when-cross-origin" },
  {
    key: "Permissions-Policy",
    value: "camera=(), microphone=(), geolocation=()",
  },
  {
    key: "Content-Security-Policy",
    value: contentSecurityPolicy(),
  },
];

const nextConfig: NextConfig = {
  async headers() {
    return [
      {
        source: "/:path*",
        headers: securityHeaders,
      },
    ];
  },
  images: {
    remotePatterns: [
      {
        protocol: "https",
        hostname: "api.dicebear.com",
        pathname: "/**",
      },
      {
        protocol: "http",
        hostname: "127.0.0.1",
        port: "8000",
        pathname: "/storage/**",
      },
      {
        protocol: "http",
        hostname: "localhost",
        port: "8000",
        pathname: "/storage/**",
      },
    ],
  },
};

/*
 * withSentryConfig uploads source maps at build time, so production stack traces
 * resolve to real files instead of minified bundles. It is a no-op without the
 * SENTRY_* build credentials, which keeps local builds and CI unaffected.
 */
export default withSentryConfig(nextConfig, {
  org: process.env.SENTRY_ORG,
  project: process.env.SENTRY_PROJECT,
  authToken: process.env.SENTRY_AUTH_TOKEN,
  silent: !process.env.CI,
  disableLogger: true,
  telemetry: false,
});
