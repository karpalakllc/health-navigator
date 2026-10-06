import { NextResponse } from "next/server";
import { t } from "@/i18n/t";

/**
 * Shared checks for every state-changing route handler under src/app/api/**.
 *
 * Those handlers are the browser's only way to write to the API, and the session
 * cookie rides along automatically. `sameSite: lax` stops a cross-site POST from
 * carrying an existing cookie, but it does nothing for login itself: a hidden
 * form on another site could POST credentials to /api/session/login as
 * `text/plain` (which `request.json()` happily parsed) and the response would
 * sign the victim into the attacker's account. Requiring our own Origin and a
 * JSON (or multipart) body closes that: a cross-site page can no longer send a
 * request this code accepts without a CORS preflight it will never pass.
 */

/** Generous for any form in the app; the longest is a forum post body. */
export const MAX_JSON_BODY_BYTES = 64 * 1024;

/** Mirrors the API's `max:5120` (KB) rule on /me/avatar. */
export const MAX_AVATAR_BYTES = 5 * 1024 * 1024;

/** Room for the multipart boundaries and part headers around the file. */
const MULTIPART_OVERHEAD_BYTES = 16 * 1024;

export type GuardResult<T> =
  { ok: true; value: T } | { ok: false; response: NextResponse };

function reject(
  message: string,
  status: number,
): { ok: false; response: NextResponse } {
  return { ok: false, response: NextResponse.json({ message }, { status }) };
}

function originOf(value: string): string | null {
  try {
    return new URL(value).origin;
  } catch {
    return null;
  }
}

/** `https://` for a bare host (Vercel's system variables carry no scheme). */
function originOfHost(value: string): string | null {
  return originOf(
    /^[a-z][a-z0-9+.-]*:\/\//i.test(value) ? value : `https://${value}`,
  );
}

/**
 * Extra origins from the runtime environment, read on every request.
 *
 * NEXT_PUBLIC_SITE_URL is inlined at build time, so it can name only one
 * origin, and the same build is often served from several: a Vercel preview
 * URL, the apex as well as www, a staging alias. Writes from any of those were
 * refused with 403 — logout included, which also stalls the stale-session
 * cleanup. None of these variables has a NEXT_PUBLIC_ prefix, and they are read
 * here, inside the request path, so Next never bakes them into the bundle.
 *
 *   ALLOWED_ORIGINS        comma-separated origins (`https://www.example.mk`)
 *   VERCEL_URL             set by Vercel per deployment (host only)
 *   VERCEL_BRANCH_URL      set by Vercel per git branch (host only)
 */
function runtimeOrigins(): string[] {
  const env = process.env;
  const origins: string[] = [];

  for (const entry of (env.ALLOWED_ORIGINS ?? "").split(",")) {
    const trimmed = entry.trim();
    const origin = trimmed ? originOf(trimmed) : null;

    if (origin && origin !== "null") {
      origins.push(origin);
    }
  }

  for (const host of [env.VERCEL_URL, env.VERCEL_BRANCH_URL]) {
    const origin = host?.trim() ? originOfHost(host.trim()) : null;

    if (origin && origin !== "null") {
      origins.push(origin);
    }
  }

  return origins;
}

/**
 * The configured public origin plus any runtime-configured ones (see
 * runtimeOrigins). Outside production the origin the request was actually
 * addressed to is accepted too, so `localhost` and `127.0.0.1` both work while
 * developing whichever one NEXT_PUBLIC_SITE_URL names.
 */
function allowedOrigins(request: Request): string[] {
  const origins: string[] = [];
  const configured = process.env.NEXT_PUBLIC_SITE_URL;
  const configuredOrigin = configured ? originOf(configured) : null;

  if (configuredOrigin) {
    origins.push(configuredOrigin);
  }

  origins.push(...runtimeOrigins());

  if (!configuredOrigin || process.env.NODE_ENV !== "production") {
    const own = originOf(request.url);
    if (own) {
      origins.push(own);
    }
  }

  return origins;
}

/**
 * Refuses a request that did not come from one of our own pages.
 *
 * Browsers send `Origin` on every POST/PATCH (an opaque context sends the
 * string "null", which matches nothing). Fetch Metadata is the fallback for the
 * rare client that omits Origin; with neither header there is no evidence the
 * request is same-origin, so it is refused.
 */
export function rejectCrossSite(request: Request): NextResponse | null {
  const origin = request.headers.get("origin");

  if (origin !== null) {
    return allowedOrigins(request).includes(origin)
      ? null
      : reject(t("errors.forbiddenOrigin"), 403).response;
  }

  return request.headers.get("sec-fetch-site") === "same-origin"
    ? null
    : reject(t("errors.forbiddenOrigin"), 403).response;
}

function mediaType(request: Request): string {
  return (request.headers.get("content-type") ?? "")
    .split(";")[0]
    .trim()
    .toLowerCase();
}

/**
 * Reads the body, giving up as soon as it passes `limit` bytes — a declared
 * Content-Length is checked first, but a chunked body has none, so the stream is
 * metered as well. Returns null when the body is too large.
 */
export async function readLimited(
  request: Request,
  limit: number,
): Promise<Uint8Array<ArrayBuffer> | null> {
  const declared = Number(request.headers.get("content-length") ?? "");

  if (Number.isFinite(declared) && declared > limit) {
    return null;
  }

  if (!request.body) {
    return new Uint8Array(0);
  }

  const reader = request.body.getReader();
  const chunks: Uint8Array[] = [];
  let total = 0;

  for (;;) {
    const { done, value } = await reader.read();

    if (done) {
      break;
    }

    total += value.byteLength;

    if (total > limit) {
      await reader.cancel().catch(() => undefined);
      return null;
    }

    chunks.push(value);
  }

  const bytes = new Uint8Array(total);
  let offset = 0;

  for (const chunk of chunks) {
    bytes.set(chunk, offset);
    offset += chunk.byteLength;
  }

  return bytes;
}

/**
 * Same-origin check, `application/json` only, bounded size, and an object at the
 * top level. Malformed JSON is the caller's mistake (400), not ours (500).
 */
export async function guardJson<T extends object>(
  request: Request,
  maxBytes = MAX_JSON_BODY_BYTES,
): Promise<GuardResult<T>> {
  const crossSite = rejectCrossSite(request);

  if (crossSite) {
    return { ok: false, response: crossSite };
  }

  if (mediaType(request) !== "application/json") {
    return reject(t("errors.unsupportedMediaType"), 415);
  }

  const bytes = await readLimited(request, maxBytes);

  if (!bytes) {
    return reject(t("errors.payloadTooLarge"), 413);
  }

  let parsed: unknown;

  try {
    parsed = JSON.parse(
      new TextDecoder("utf-8", { fatal: true }).decode(bytes),
    );
  } catch {
    return reject(t("errors.invalidJson"), 400);
  }

  if (parsed === null || typeof parsed !== "object" || Array.isArray(parsed)) {
    return reject(t("errors.invalidJson"), 400);
  }

  return { ok: true, value: parsed as T };
}

/** Same-origin check, `multipart/form-data` only, bounded size. */
export async function guardMultipart(
  request: Request,
  maxBytes = MAX_AVATAR_BYTES + MULTIPART_OVERHEAD_BYTES,
): Promise<GuardResult<FormData>> {
  const crossSite = rejectCrossSite(request);

  if (crossSite) {
    return { ok: false, response: crossSite };
  }

  if (mediaType(request) !== "multipart/form-data") {
    return reject(t("errors.unsupportedMediaType"), 415);
  }

  const bytes = await readLimited(request, maxBytes);

  if (!bytes) {
    return reject(t("errors.payloadTooLarge"), 413);
  }

  try {
    const form = await new Response(bytes, {
      headers: { "Content-Type": request.headers.get("content-type") ?? "" },
    }).formData();

    return { ok: true, value: form };
  } catch {
    return reject(t("errors.invalidJson"), 400);
  }
}

/**
 * Leading bytes of the formats the API's `image` rule accepts (jpg, png, gif,
 * bmp, webp). Checked here so a renamed text file or an oversized upload is
 * turned away before it costs an authenticated round trip.
 */
function looksLikeImage(head: Uint8Array): boolean {
  const startsWith = (...bytes: number[]) =>
    bytes.every((byte, index) => head[index] === byte);

  return (
    startsWith(0xff, 0xd8, 0xff) || // JPEG
    startsWith(0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a) || // PNG
    startsWith(0x47, 0x49, 0x46, 0x38) || // GIF8
    startsWith(0x42, 0x4d) || // BMP
    (startsWith(0x52, 0x49, 0x46, 0x46) && // RIFF....WEBP
      head[8] === 0x57 &&
      head[9] === 0x45 &&
      head[10] === 0x42 &&
      head[11] === 0x50)
  );
}

/** The `avatar` part, if it is a real, non-empty image file within the cap. */
export async function avatarFromForm(form: FormData): Promise<File | null> {
  const avatar = form.get("avatar");

  if (!(avatar instanceof File)) {
    return null;
  }

  if (avatar.size === 0 || avatar.size > MAX_AVATAR_BYTES) {
    return null;
  }

  const head = new Uint8Array(await avatar.slice(0, 12).arrayBuffer());

  return looksLikeImage(head) ? avatar : null;
}
