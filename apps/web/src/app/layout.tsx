import type { Metadata } from "next";
import { Geist, Geist_Mono } from "next/font/google";
import { PlausibleAnalytics } from "@/components/layout/plausible-analytics";
import { SearchDialogProvider } from "@/components/layout/search-dialog-context";
import { SiteFooter } from "@/components/layout/site-footer";
import { SiteHeader } from "@/components/layout/site-header";
import { mk } from "@/i18n/mk";
import "./globals.css";

const geistSans = Geist({
  variable: "--font-geist-sans",
  subsets: ["latin"],
});

const geistMono = Geist_Mono({
  variable: "--font-geist-mono",
  subsets: ["latin"],
});

export const metadata: Metadata = {
  title: mk.meta.title,
  description: mk.meta.description,
};

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
