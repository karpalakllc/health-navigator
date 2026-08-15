import type { Metadata } from "next";
import { Geist, Geist_Mono, Inter } from "next/font/google";
import { PlausibleAnalytics } from "@/components/layout/plausible-analytics";
import { SearchDialogProvider } from "@/components/layout/search-dialog-context";
import { SiteMaintenanceGate } from "@/components/layout/site-maintenance-gate";
import { SitePlaceholdersProvider } from "@/components/layout/site-placeholders-provider";
import { SiteFooter } from "@/components/layout/site-footer";
import { SiteHeader } from "@/components/layout/site-header";
import { fetchPublicSettingsServer } from "@/lib/api/settings";
import { mk } from "@/i18n/mk";
import { cn } from "@/lib/cn";
import "./globals.css";

export const dynamic = "force-dynamic";

const geistSans = Geist({
  variable: "--font-geist-sans",
  subsets: ["latin"],
});

const geistMono = Geist_Mono({
  variable: "--font-geist-mono",
  subsets: ["latin"],
});

const inter = Inter({
  variable: "--font-inter",
  subsets: ["latin"],
});

export async function generateMetadata(): Promise<Metadata> {
  const settings = await fetchPublicSettingsServer();
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
  };
}

export default async function RootLayout({
  children,
}: Readonly<{
  children: React.ReactNode;
}>) {
  const settings = await fetchPublicSettingsServer();
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
