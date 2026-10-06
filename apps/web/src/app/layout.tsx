import type { Metadata } from "next";
import { Geist, Geist_Mono, Inter } from "next/font/google";
import { PlausibleAnalytics } from "@/components/layout/plausible-analytics";
import { SearchDialogProvider } from "@/components/layout/search-dialog-context";
import { SiteMaintenanceGate } from "@/components/layout/site-maintenance-gate";
import { SitePlaceholdersProvider } from "@/components/layout/site-placeholders-provider";
import { SiteFooter } from "@/components/layout/site-footer";
import { SiteHeader } from "@/components/layout/site-header";
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
 */
const geistSans = Geist({
  variable: "--font-geist-sans",
  subsets: ["latin", "cyrillic"],
});

const geistMono = Geist_Mono({
  variable: "--font-geist-mono",
  subsets: ["latin", "cyrillic"],
});

const inter = Inter({
  variable: "--font-inter",
  subsets: ["latin", "cyrillic"],
});

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
  const fontClass =
    settings.site_font_family === "inter"
      ? `${inter.variable} font-[family-name:var(--font-inter)]`
      : settings.site_font_family === "system"
        ? "font-sans"
        : `${geistSans.variable} ${geistMono.variable}`;

  return (
    <html
      lang="mk"
      className={cn(fontClass, "h-full antialiased")}
      suppressHydrationWarning
    >
      {/* Extensions (e.g. ColorZilla) mutate <body> before hydrate — suppress only on body */}
      <body
        className="relative flex min-h-full flex-col overflow-x-clip bg-background text-foreground"
        suppressHydrationWarning
      >
        <div className="app-ambient" aria-hidden="true" />
        <SiteMaintenanceGate>
          <SitePlaceholdersProvider settings={settings}>
            <SearchDialogProvider>
              <PlausibleAnalytics />
              <SiteHeader />
              <div className="flex-1">{children}</div>
              <SiteFooter />
            </SearchDialogProvider>
          </SitePlaceholdersProvider>
        </SiteMaintenanceGate>
      </body>
    </html>
  );
}
