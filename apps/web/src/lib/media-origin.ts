/*
 * Where uploaded media (logos, avatars) is served from when it does not live on
 * the API host — an object-storage bucket or the CDN in front of it. The API
 * builds those URLs from AWS_URL; NEXT_PUBLIC_MEDIA_URL must name the same
 * public base so the CSP img-src (src/proxy.ts) and next/image remotePatterns
 * (next.config.ts) let the browser load them.
 *
 * Imported by next.config.ts, so this file must stay free of "@/" imports and
 * anything server- or browser-only.
 */

import type { NextConfig } from "next";

export const MEDIA_URL_ENV = "NEXT_PUBLIC_MEDIA_URL";

type RemotePattern = NonNullable<
  NonNullable<NextConfig["images"]>["remotePatterns"]
>[number];

export type MediaUrlResult =
  { url: URL | null; error?: undefined } | { url: null; error: string };

/**
 * Parses the media base URL. Unset is fine (media is on the API host). An http
 * base is accepted only outside production: a production page is https, and
 * an http image there is mixed content the browser blocks anyway.
 */
export function resolveMediaUrl(
  value: string | undefined,
  isProduction: boolean,
): MediaUrlResult {
  const raw = value?.trim();
  if (!raw) return { url: null };

  let url: URL;
  try {
    url = new URL(raw);
  } catch {
    return { url: null, error: `${MEDIA_URL_ENV} is not a valid URL.` };
  }

  if (url.protocol === "https:") return { url };
  if (url.protocol === "http:" && !isProduction) return { url };

  return {
    url: null,
    error: isProduction
      ? `${MEDIA_URL_ENV} must be an https URL in production.`
      : `${MEDIA_URL_ENV} must be an http(s) URL.`,
  };
}

/** The origin to add to img-src, or "" when there is no (usable) media host. */
export function mediaImgSrc(
  value: string | undefined,
  isProduction: boolean,
): string {
  return resolveMediaUrl(value, isProduction).url?.origin ?? "";
}

/**
 * next/image patterns for the media host, limited to the base URL's path so
 * the optimiser cannot be pointed at anything else on that host.
 */
export function mediaRemotePatterns(
  value: string | undefined,
  isProduction: boolean,
): RemotePattern[] {
  const { url } = resolveMediaUrl(value, isProduction);
  if (!url) return [];

  const basePath = url.pathname.replace(/\/+$/, "");

  return [
    {
      protocol: url.protocol === "https:" ? "https" : "http",
      hostname: url.hostname,
      ...(url.port ? { port: url.port } : {}),
      pathname: `${basePath}/**`,
    },
  ];
}
