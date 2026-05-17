import type { Metadata } from "next";
import { Geist, Geist_Mono } from "next/font/google";
import { PlausibleAnalytics } from "@/components/layout/plausible-analytics";
import { SearchDialogProvider } from "@/components/layout/search-dialog-context";
import { SiteFooter } from "@/components/layout/site-footer";
import { SiteHeader } from "@/components/layout/site-header";
import { fetchPublicSettingsServer } from "@/lib/api/settings";
import { mk } from "@/i18n/mk";
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

export default function RootLayout({
  children,
}: Readonly<{
  children: React.ReactNode;
}>) {
  return (
    <html
      lang="mk"
      className={`${geistSans.variable} ${geistMono.variable} h-full antialiased`}
      suppressHydrationWarning
    >
      {/* Extensions (e.g. ColorZilla) mutate <body> before hydrate — suppress only on body */}
      <body
        className="relative flex min-h-full flex-col bg-background text-foreground"
        suppressHydrationWarning
      >
        <div className="app-ambient" aria-hidden="true" />
        <SearchDialogProvider>
          <PlausibleAnalytics />
          <SiteHeader />
          <div className="flex-1">{children}</div>
          <SiteFooter />
        </SearchDialogProvider>
      </body>
    </html>
  );
}
