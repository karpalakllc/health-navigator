import { SiteHeaderBar } from "@/components/layout/site-header-bar";
import { getSessionToken } from "@/lib/auth/session";

export async function SiteHeader() {
  const token = await getSessionToken();

  return (
    <header className="sticky top-0 z-50 border-b border-border/80 glass">
      <div className="flex flex-col">
        <SiteHeaderBar isLoggedIn={Boolean(token)} />
      </div>
    </header>
  );
}
