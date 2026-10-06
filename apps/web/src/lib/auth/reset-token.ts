/**
 * Carrying a password-reset link's token and address off the URL.
 *
 * The emailed link is /reset-password?token=…&email=…. Rendered as a page, that
 * URL is what every script on it sees: Plausible reports `location.href`, and
 * Sentry attaches the URL to any error. A live reset token in a third party's
 * logs is a takeover waiting to happen. So /reset-password is a route handler
 * that moves both values into a short-lived httpOnly cookie and redirects to the
 * form at a clean URL — no HTML, and no script, ever runs at the address that
 * carries the token.
 */

export const RESET_COOKIE = "zdravje_password_reset";

/** Where the form lives; the cookie is scoped to it (and the redirect to it). */
export const RESET_FORM_PATH = "/reset-password/new";

/** Laravel's default broker expiry; the cookie need not outlive the token. */
const RESET_COOKIE_MAX_AGE_SECONDS = 60 * 60;

export type ResetCredentials = { token: string; email: string };

export function resetCookieOptions(
  maxAgeSeconds = RESET_COOKIE_MAX_AGE_SECONDS,
) {
  return {
    httpOnly: true,
    secure: process.env.NODE_ENV === "production",
    // Lax, not strict: the cookie is set on a navigation that started in a mail
    // client, and strict would withhold it from the redirect that follows.
    sameSite: "lax" as const,
    path: RESET_FORM_PATH,
    maxAge: maxAgeSeconds,
  };
}

/** Reads token and email from a query string, or null if either is missing. */
export function resetCredentialsFromQuery(
  search: URLSearchParams,
): ResetCredentials | null {
  const token = search.get("token")?.trim();
  const email = search.get("email")?.trim();

  return token && email ? { token, email } : null;
}

export function encodeResetCookie(credentials: ResetCredentials): string {
  return JSON.stringify({ token: credentials.token, email: credentials.email });
}

export function decodeResetCookie(
  value: string | undefined,
): ResetCredentials | null {
  if (!value) {
    return null;
  }

  try {
    const parsed: unknown = JSON.parse(value);

    if (
      parsed !== null &&
      typeof parsed === "object" &&
      "token" in parsed &&
      "email" in parsed &&
      typeof parsed.token === "string" &&
      typeof parsed.email === "string" &&
      parsed.token !== "" &&
      parsed.email !== ""
    ) {
      return { token: parsed.token, email: parsed.email };
    }
  } catch {
    // A tampered or truncated cookie is treated as absent.
  }

  return null;
}
