import type { NextConfig } from "next";
import { withSentryConfig } from "@sentry/nextjs";

/*
 * Content-Security-Policy is NOT set here. It is minted per request in
 * src/proxy.ts so each response can carry a fresh script nonce; a static header
 * would shadow that and force `unsafe-inline` back in.
 */
const securityHeaders = [
  { key: "X-Frame-Options", value: "DENY" },
  { key: "X-Content-Type-Options", value: "nosniff" },
  { key: "Referrer-Policy", value: "strict-origin-when-cross-origin" },
  {
    key: "Permissions-Policy",
    value: "camera=(), microphone=(), geolocation=()",
  },
];

/*
 * The local Laravel API serves uploads over plain http on :8000. Only a dev
 * server may optimise images from there: in a production build these entries
 * let anyone make the image optimiser fetch from the server's own loopback port,
 * and buy nothing — uploaded avatars are rendered with plain <img> tags.
 */
const localApiStoragePatterns =
  process.env.NODE_ENV === "production"
    ? []
    : (["127.0.0.1", "localhost"] as const).map((hostname) => ({
        protocol: "http" as const,
        hostname,
        port: "8000",
        pathname: "/storage/**",
      }));

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
      ...localApiStoragePatterns,
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
