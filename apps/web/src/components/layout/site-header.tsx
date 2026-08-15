import { SiteHeaderBar } from "@/components/layout/site-header-bar";
import { StaleSessionCleanup } from "@/components/layout/stale-session-cleanup";
import { fetchPublicSettingsServer } from "@/lib/api/settings";
import { fetchMe } from "@/lib/api/me";
import { ApiRequestError } from "@/lib/api/server";
import { getSessionToken } from "@/lib/auth/session";

export async function SiteHeader() {
  const token = await getSessionToken();
  const settings = await fetchPublicSettingsServer();

  let user = null;

  if (token) {
    try {
      user = await fetchMe();
    } catch (error) {
      if (!(error instanceof ApiRequestError && error.status === 401)) {
        throw error;
      }
    }
  }

  // A token the API rejected is not a session. Keying the header off `user`
  // rather than `token` stops SiteHeaderBar rendering a placeholder account menu
  // for a signed-out visitor; StaleSessionCleanup then clears the dead cookie.
  const hasStaleSession = Boolean(token) && user === null;

  return (
    <header className="sticky top-0 z-50 border-b border-border/80 glass">
      {hasStaleSession ? <StaleSessionCleanup /> : null}
      <div className="flex flex-col">
        <SiteHeaderBar
          isLoggedIn={Boolean(user)}
          logoUrl={settings.logo_url}
          user={user}
        />
      </div>
    </header>
  );
}
