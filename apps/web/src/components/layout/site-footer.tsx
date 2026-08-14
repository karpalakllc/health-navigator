import Link from "next/link";
import { FooterSearchButton } from "@/components/layout/footer-search-button";
import { pageContainerClass } from "@/components/ui/layout";
import { cn } from "@/lib/cn";
import type { PublicSettings } from "@/lib/api/settings";
import { fetchPublicSettingsServer } from "@/lib/api/settings";
import { t, tFormat } from "@/i18n/t";

const linkClass = "text-sm text-muted-foreground transition hover:text-primary";

export async function SiteFooter() {
  const settings = await fetchPublicSettingsServer();

  return <SiteFooterContent settings={settings} />;
}

export function SiteFooterContent({ settings }: { settings: PublicSettings }) {
  const year = new Date().getFullYear();

  return (
    <footer className="mt-auto border-t border-border bg-muted/30">
      <div className={`${pageContainerClass} py-10`}>
        <div className="grid gap-8 sm:grid-cols-2 lg:grid-cols-4 lg:gap-10">
          <div className="space-y-3">
            <p className="text-sm font-semibold text-foreground">
              {t("meta.title")}
            </p>
            <p className="text-sm leading-relaxed text-muted-foreground">
              {t("footer.informational")}
            </p>
          </div>

          <div className="space-y-3">
            <p className="text-sm font-semibold text-foreground">
              {t("footer.directory")}
            </p>
            <ul className="flex flex-col gap-2">
              <li>
                <Link href="/doctors" className={linkClass}>
                  {t("nav.doctors")}
                </Link>
              </li>
              <li>
                <Link href="/facilities" className={linkClass}>
                  {t("nav.facilities")}
                </Link>
              </li>
              <li>
                <Link href="/pharmacies" className={linkClass}>
                  {t("nav.pharmacies")}
                </Link>
              </li>
              <li>
                <Link href="/products" className={linkClass}>
                  {t("nav.products")}
                </Link>
              </li>
            </ul>
          </div>

          <div className="space-y-3">
            <p className="text-sm font-semibold text-foreground">
              {t("footer.resources")}
            </p>
            <ul className="flex flex-col gap-2">
              <li>
                <Link href="/about" className={linkClass}>
                  {t("footer.about")}
                </Link>
              </li>
              <li>
                <Link href="/guidance" className={linkClass}>
                  {t("nav.guidance")}
                </Link>
              </li>
              <li>
                <Link href="/forum" className={linkClass}>
                  {t("nav.forum")}
                </Link>
              </li>
              <li>
                <FooterSearchButton
                  className={cn(
                    linkClass,
                    "inline-block text-left font-[inherit]",
                  )}
                />
              </li>
            </ul>
          </div>

          <div className="space-y-3">
            <p className="text-sm font-semibold text-foreground">
              {t("footer.legal")}
            </p>
            <ul className="flex flex-col gap-2">
              <li>
                <Link href="/privacy" className={linkClass}>
                  {t("footer.privacy")}
                </Link>
              </li>
              <li>
                <Link href="/terms" className={linkClass}>
                  {t("footer.terms")}
                </Link>
              </li>
              <li>
                <Link href="/disclaimer" className={linkClass}>
                  {t("footer.disclaimer")}
                </Link>
              </li>
            </ul>
          </div>
        </div>

        <div
          className={cn(
            "mt-10 rounded-2xl border border-destructive/20 bg-destructive/5 px-4 py-3 text-center text-sm text-destructive",
          )}
        >
          {settings.footer_emergency_text}
        </div>

        <p className="mt-6 text-xs leading-relaxed text-muted-foreground">
          {settings.footer_disclaimer_text}
        </p>

        <p className="mt-4 text-center text-xs text-muted-foreground">
          {tFormat("footer.copyright", {
            year,
            name: settings.copyright_name,
          })}
        </p>
      </div>
    </footer>
  );
}
