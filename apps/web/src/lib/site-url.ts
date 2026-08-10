/**
 * Public origin of the web app, used for canonical URLs, OpenGraph, robots and
 * the sitemap. Falls back to localhost so local development and CI builds work
 * without extra configuration; production must set NEXT_PUBLIC_SITE_URL.
 */
export function siteUrl(): string {
  const raw = process.env.NEXT_PUBLIC_SITE_URL ?? "http://127.0.0.1:3000";

  return raw.replace(/\/$/, "");
}

export function absoluteUrl(path: string): string {
  return `${siteUrl()}${path.startsWith("/") ? path : `/${path}`}`;
}
