import { BottomTabBar } from "@/components/layout/bottom-tab-bar";
import { HeaderScrollState } from "@/components/layout/header-scroll-state";
import { SiteHeaderBar } from "@/components/layout/site-header-bar";
import { StaleSessionCleanup } from "@/components/layout/stale-session-cleanup";
import { fetchPublicSettings } from "@/lib/api/settings";
import { getShellSession } from "@/lib/auth/header-session";
import { moduleFlags } from "@/lib/site-modules";

export async function SiteHeader() {
  const settings = await fetchPublicSettings();
  const { user, isLoggedIn, hasStaleSession } = await getShellSession();

  return (
    // Cream, no rule until the page scrolls (HeaderScrollState sets
    // data-scrolled); desktop always has the rule under the nav row.
    <header
      id="site-header"
      className="sticky top-0 z-50 border-b border-transparent bg-cream data-[scrolled]:border-line lg:border-line"
    >
      {hasStaleSession ? <StaleSessionCleanup /> : null}
      <HeaderScrollState />
      <SiteHeaderBar
        isLoggedIn={isLoggedIn}
        logoUrl={settings.logo_url}
        user={user}
        modules={moduleFlags(settings)}
      />
    </header>
  );
}

/**
 * The mobile bottom tab bar, rendered by the layout after the footer so it
 * comes last in the tab order (it is fixed to the bottom visually). Its own
 * "Главна навигација" landmark, outside the banner.
 */
export async function SiteTabBar() {
  const settings = await fetchPublicSettings();
  const { isLoggedIn } = await getShellSession();

  return (
    <BottomTabBar isLoggedIn={isLoggedIn} modules={moduleFlags(settings)} />
  );
}
