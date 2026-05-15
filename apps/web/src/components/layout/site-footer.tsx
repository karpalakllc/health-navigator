import Link from "next/link";
import { FooterSearchButton } from "@/components/layout/footer-search-button";
import { pageContainerClass } from "@/components/ui/layout";
import { cn } from "@/lib/cn";
import { t } from "@/i18n/t";

const linkClass = "text-sm text-muted-foreground transition hover:text-primary";

export function SiteFooter() {
  return (
    <footer className="mt-auto border-t border-border bg-muted/30">
      <div className={`${pageContainerClass} py-10`}>
        <div className="grid gap-8 sm:grid-cols-2 lg:grid-cols-4 lg:gap-10">
          <div className="space-y-3">
            <p className="text-sm font-semibold text-foreground">{t("meta.title")}</p>
            <p className="text-sm leading-relaxed text-muted-foreground">{t("footer.informational")}</p>
          </div>

          <div className="space-y-3">
            <p className="text-sm font-semibold text-foreground">{t("footer.directory")}</p>
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
            <p className="text-sm font-semibold text-foreground">{t("footer.resources")}</p>
            <ul className="flex flex-col gap-2">
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
                  className={cn(linkClass, "inline-block text-left font-[inherit]")}
                />
              </li>
            </ul>
          </div>

          <div className="space-y-3">
            <p className="text-sm font-semibold text-foreground">{t("footer.legal")}</p>
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
          {t("footer.emergency")} <strong>194</strong> {t("footer.emergencyOr")}{" "}
          <strong>112</strong> {t("footer.emergencyEnd")}
        </div>

        <p className="mt-6 text-xs leading-relaxed text-muted-foreground">
          {t("footer.reviewsNote")}{" "}
          <Link href="/guidance" className="underline hover:text-foreground">
            {t("footer.guidanceLink")}
          </Link>{" "}
          {t("footer.guidanceNote")}
        </p>
      </div>
    </footer>
  );
}
