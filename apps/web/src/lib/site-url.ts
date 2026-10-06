/**
 * Public origin of the web app: canonical URLs, OpenGraph, robots.txt, sitemap.
 *
 * NEXT_PUBLIC_* is inlined at build time, so a production build made without this
 * variable bakes the fallback into the shipped artifact — setting it in the runtime
 * environment afterwards cannot fix it. A build with no origin silently ships
 * `Sitemap: http://127.0.0.1:3000/sitemap.xml` in a static robots.txt, so failing
 * the build is the only honest option.
 */
export function siteUrl(): string {
  const raw = process.env.NEXT_PUBLIC_SITE_URL;

  if (!raw) {
    if (process.env.NODE_ENV === "production") {
      throw new Error(
        "NEXT_PUBLIC_SITE_URL must be set at BUILD time. It is inlined into the " +
          "bundle, so setting it only in the runtime environment leaves localhost " +
          "in robots.txt, the sitemap and every canonical URL.",
      );
    }

    return "http://127.0.0.1:3000";
  }

  return raw.replace(/\/$/, "");
}

/** Whether a configured origin is https. Unset or unparsable is not. */
export function isHttpsOrigin(raw: string | undefined): boolean {
  if (!raw) {
    return false;
  }

  try {
    return new URL(raw).protocol === "https:";
  } catch {
    return false;
  }
}

/**
 * Whether cookies the app sets must carry `Secure`. Keyed on the public origin,
 * not NODE_ENV: a non-production build served over https still needs it. With no
 * origin configured it falls back to NODE_ENV (siteUrl() refuses that in a
 * production build anyway).
 */
export function secureCookies(): boolean {
  const raw = process.env.NEXT_PUBLIC_SITE_URL;

  return raw ? isHttpsOrigin(raw) : process.env.NODE_ENV === "production";
}

export function absoluteUrl(path: string): string {
  return `${siteUrl()}${path.startsWith("/") ? path : `/${path}`}`;
}
