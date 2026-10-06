import * as Sentry from "@sentry/nextjs";
import { SiteHeaderBar } from "@/components/layout/site-header-bar";
import { StaleSessionCleanup } from "@/components/layout/stale-session-cleanup";
import { fetchPublicSettings } from "@/lib/api/settings";
import { fetchMe } from "@/lib/api/me";
import { ApiRequestError } from "@/lib/api/server";
import { getSessionToken } from "@/lib/auth/session";
import { moduleFlags } from "@/lib/site-modules";

export async function SiteHeader() {
  const token = await getSessionToken();
  const settings = await fetchPublicSettings();

  let user = null;
  let sessionRejected = false;

  if (token) {
    try {
      user = await fetchMe();
    } catch (error) {
      if (error instanceof ApiRequestError && error.status === 401) {
        sessionRejected = true;
      } else {
        // The header sits in the root layout, outside every error boundary
        // but global-error. Rethrowing here turned any /me hiccup (5xx, 429,
        // network) into a bare error page on every route for every signed-in
        // visitor. Render the signed-in shell without account details instead.
        Sentry.captureException(error);
      }
    }
  }

  // Only a token the API actually rejected (401) is a dead session. Keying the
  // header off that rather than `token` stops SiteHeaderBar rendering a
  // placeholder account menu for a signed-out visitor; StaleSessionCleanup then
  // clears the dead cookie. Any other failure keeps the session and the cookie.
  const hasStaleSession = sessionRejected;
  const isLoggedIn = Boolean(token) && !sessionRejected;

  return (
    <header className="sticky top-0 z-50 border-b border-border/80 glass">
      {hasStaleSession ? <StaleSessionCleanup /> : null}
      <div className="flex flex-col">
        <SiteHeaderBar
          isLoggedIn={isLoggedIn}
          logoUrl={settings.logo_url}
          user={user}
          modules={moduleFlags(settings)}
        />
      </div>
    </header>
  );
}
