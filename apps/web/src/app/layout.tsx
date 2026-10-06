import type { Metadata, Viewport } from "next";
import { Geologica, Source_Sans_3 } from "next/font/google";
import { PlausibleAnalytics } from "@/components/layout/plausible-analytics";
import { SearchDialogProvider } from "@/components/layout/search-dialog-context";
import { SiteMaintenanceGate } from "@/components/layout/site-maintenance-gate";
import { SitePlaceholdersProvider } from "@/components/layout/site-placeholders-provider";
import { SiteFooter } from "@/components/layout/site-footer";
import { SiteHeader, SiteTabBar } from "@/components/layout/site-header";
import { MAIN_CONTENT_ID, SkipLink } from "@/components/layout/skip-link";
import { fetchPublicSettings } from "@/lib/api/settings";
import { mk } from "@/i18n/mk";
import { cn } from "@/lib/cn";
import "./globals.css";

export const dynamic = "force-dynamic";

/*
 * The site is Macedonian, so every face needs the Cyrillic subset — with only
 * "latin" the text fell back to a system font. Basic "cyrillic" (U+0400–045F)
 * holds the whole alphabet, ѓ ќ ѕ љ њ џ ј included; "cyrillic-ext" covers other
 * languages' letters and would only add a preloaded file per face.
 *
 * Geologica: UI, headings, buttons, meta. Source Sans 3: reading text only
 * (bios, reviews, posts, guidance body), via the `font-reading` /
 * `type-reading` utilities. Both are variable fonts (weights 400–700 used).
 */
const geologica = Geologica({
  variable: "--font-geologica",
  subsets: ["latin", "cyrillic"],
  display: "swap",
});

const sourceSans = Source_Sans_3({
  variable: "--font-source-sans",
  subsets: ["latin", "cyrillic"],
  style: ["normal", "italic"],
  display: "swap",
});

export const viewport: Viewport = {
  // env(safe-area-inset-bottom) is only non-zero with viewport-fit=cover;
  // the bottom tab bar pads itself by it.
  viewportFit: "cover",
  themeColor: "#fbf6f1",
};

export async function generateMetadata(): Promise<Metadata> {
  const settings = await fetchPublicSettings();
  const faviconCacheKey = settings.favicon_url?.split("/").pop() ?? "default";

  return {
    title: mk.meta.title,
    description: mk.meta.description,
    icons: settings.favicon_url
      ? {
          icon: `${settings.favicon_url}?v=${faviconCacheKey}`,
          shortcut: `${settings.favicon_url}?v=${faviconCacheKey}`,
        }
      : undefined,
    // The maintenance page answers 200 in place of every route; it must not be
    // indexed as their content. Pages only override `robots` to tighten it.
    ...(settings.maintenance_mode
      ? { robots: { index: false, follow: false } }
      : {}),
  };
}

export default async function RootLayout({
  children,
}: Readonly<{
  children: React.ReactNode;
}>) {
  const settings = await fetchPublicSettings();

  // settings.site_font_family (geist/inter/system) is no longer honoured:
  // the D2a type system is Geologica + Source Sans 3.
  return (
    <html
      lang="mk"
      className={cn(geologica.variable, sourceSans.variable, "h-full")}
      suppressHydrationWarning
    >
      {/* Extensions (e.g. ColorZilla) mutate <body> before hydrate — suppress only on body */}
      <body
        className="relative flex min-h-full flex-col overflow-x-clip bg-background text-foreground"
        suppressHydrationWarning
      >
        <SiteMaintenanceGate>
          <SitePlaceholdersProvider settings={settings}>
            <SearchDialogProvider>
              <PlausibleAnalytics />
              <SkipLink />
              <SiteHeader />
              {/* The one <main> of the page (PageShell is a <div>), so it also
                  covers the hero; tabIndex lets the skip link move focus here. */}
              <main
                id={MAIN_CONTENT_ID}
                tabIndex={-1}
                className="flex-1 outline-none"
              >
                {children}
              </main>
              <SiteFooter />
              <SiteTabBar />
            </SearchDialogProvider>
          </SitePlaceholdersProvider>
        </SiteMaintenanceGate>
      </body>
    </html>
  );
}
