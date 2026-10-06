import * as Sentry from "@sentry/nextjs";
import { cache } from "react";
import { fetchMe, type AuthUser } from "@/lib/api/me";
import { ApiRequestError } from "@/lib/api/server";
import { getSessionToken } from "@/lib/auth/session";

export type ShellSession = {
  user: AuthUser | null;
  /** Signed in (the API did not reject the token). */
  isLoggedIn: boolean;
  /** The API answered 401: the cookie is dead and must be cleared. */
  hasStaleSession: boolean;
};

/**
 * Who the site shell (header + bottom tab bar) is drawn for. Memoised per
 * request so the header and the tab bar share one /me call.
 */
export const getShellSession = cache(async (): Promise<ShellSession> => {
  const token = await getSessionToken();

  let user: AuthUser | null = null;
  let sessionRejected = false;

  if (token) {
    try {
      user = await fetchMe();
    } catch (error) {
      if (error instanceof ApiRequestError && error.status === 401) {
        sessionRejected = true;
      } else {
        // The shell sits in the root layout, outside every error boundary
        // but global-error. Rethrowing here turned any /me hiccup (5xx, 429,
        // network) into a bare error page on every route for every signed-in
        // visitor. Render the signed-in shell without account details instead.
        Sentry.captureException(error);
      }
    }
  }

  // Only a token the API actually rejected (401) is a dead session. Keying the
  // shell off that rather than `token` stops it rendering a placeholder
  // account menu for a signed-out visitor; StaleSessionCleanup then clears the
  // dead cookie. Any other failure keeps the session and the cookie.
  return {
    user,
    isLoggedIn: Boolean(token) && !sessionRejected,
    hasStaleSession: sessionRejected,
  };
});
