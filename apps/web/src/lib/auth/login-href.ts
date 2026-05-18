/** Safe internal login URL with optional post-auth redirect. */
export function loginHref(redirectTo?: string | null): string {
  if (!redirectTo) {
    return "/login";
  }

  const path = redirectTo.trim();

  if (!path.startsWith("/") || path.startsWith("//")) {
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

  const path = redirect.trim();

  if (!path.startsWith("/") || path.startsWith("//")) {
    return fallback;
  }

  return path;
}
