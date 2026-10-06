/**
 * Origin used only to resolve a candidate path. `.invalid` is reserved (RFC 2606),
 * so it can never collide with a real host an attacker controls.
 */
const PROBE_ORIGIN = "https://redirect.invalid";

/**
 * Returns `path` as a same-origin path+search+hash, or null if it would leave the
 * site.
 *
 * Checking only for a leading `//` is not enough: browsers treat `\` as `/` and
 * strip tabs and newlines from URLs, so `/\evil.com`, `/\/evil.com` and
 * `/<TAB>/evil.com` all navigate to evil.com. Rather than enumerate those quirks,
 * resolve the path the way a browser would and require that it stays on our
 * origin — and refuse backslashes and control characters outright, since no
 * legitimate internal link contains them.
 */
function sameOriginPath(raw: string): string | null {
  const path = raw.trim();

  if (!path.startsWith("/") || path.startsWith("//")) {
    return null;
  }

  if (/[\\\u0000-\u001f\u007f]/.test(path)) {
    return null;
  }

  let resolved: URL;

  try {
    resolved = new URL(path, PROBE_ORIGIN);
  } catch {
    return null;
  }

  if (resolved.origin !== PROBE_ORIGIN) {
    return null;
  }

  const result = `${resolved.pathname}${resolved.search}${resolved.hash}`;

  // A pathname that normalises to `//host` (e.g. `/.//evil.example`) would be
  // read as protocol-relative by whoever navigates to it next.
  return result.startsWith("//") ? null : result;
}

/** Safe internal login URL with optional post-auth redirect. */
export function loginHref(redirectTo?: string | null): string {
  if (!redirectTo) {
    return "/login";
  }

  const path = sameOriginPath(redirectTo);

  if (!path) {
    return "/login";
  }

  if (path === "/login" || path.startsWith("/login?")) {
    return "/login";
  }

  return `/login?redirect=${encodeURIComponent(path)}`;
}

export function safeRedirectTarget(
  redirect: string | null | undefined,
  fallback = "/",
): string {
  if (!redirect) {
    return fallback;
  }

  return sameOriginPath(redirect) ?? fallback;
}

/**
 * The username chooser, returning to `redirectTo` afterwards: for members who
 * still have a temporary „clen-…“ name and want to post (the API refuses
 * public writes until they choose).
 */
export function chooseUsernameHref(redirectTo?: string | null): string {
  const path = redirectTo ? sameOriginPath(redirectTo) : null;

  return path
    ? `/account/username?redirect=${encodeURIComponent(path)}`
    : "/account/username";
}
