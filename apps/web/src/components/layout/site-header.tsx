import { SiteHeaderBar } from "@/components/layout/site-header-bar";
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

  return (
    <header className="sticky top-0 z-50 border-b border-border/80 glass">
      <div className="flex flex-col">
        <SiteHeaderBar
          isLoggedIn={Boolean(token)}
          logoUrl={settings.logo_url}
          user={user}
        />
      </div>
    </header>
  );
}
