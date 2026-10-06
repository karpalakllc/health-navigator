import type { NextConfig } from "next";
import { withSentryConfig } from "@sentry/nextjs";
import { mediaRemotePatterns, resolveMediaUrl } from "./src/lib/media-origin";
import { htmlLimitedBotsPattern } from "./src/lib/crawlers";
import { securityHeaders } from "./src/lib/security-headers";

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

/*
 * Media on object storage (NEXT_PUBLIC_MEDIA_URL = the API's AWS_URL). A bad
 * value fails the build: ignoring it would ship a site whose every logo and
 * avatar is blocked, with nothing in the logs saying why.
 */
const isProduction = process.env.NODE_ENV === "production";
const media = resolveMediaUrl(process.env.NEXT_PUBLIC_MEDIA_URL, isProduction);
if (media.error) {
  throw new Error(media.error);
}

const nextConfig: NextConfig = {
  // Search and AI crawlers get <title>/canonical in <head> and a real 404
  // status (see src/lib/crawlers.ts, docs/seo.md).
  htmlLimitedBots: htmlLimitedBotsPattern(),
  async headers() {
    return [
      {
        source: "/:path*",
        // CSP is set per request in src/proxy.ts; see security-headers.ts.
        headers: securityHeaders(process.env.NEXT_PUBLIC_SITE_URL),
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
      ...mediaRemotePatterns(process.env.NEXT_PUBLIC_MEDIA_URL, isProduction),
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
