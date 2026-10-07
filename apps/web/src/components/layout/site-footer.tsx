import Link from "next/link";
import type { ReactNode } from "react";
import { FooterSearchButton } from "@/components/layout/footer-search-button";
import { FIRST_AID_COPY } from "@/content/first-aid/copy";
import {
  FIRST_AID_BASE,
  hasPublishedFirstAidGuides,
} from "@/content/first-aid";
import { SiteBrandMark } from "@/components/layout/site-brand-mark";
import { isPathEnabled } from "@/lib/site-modules";
import type { PublicSettings } from "@/lib/api/settings";
import { fetchPublicSettings } from "@/lib/api/settings";
import { t, tFormat } from "@/i18n/t";

// 48px rows on mobile (WCAG 2.5.8 with room to spare), 40px on desktop.
const linkClass =
  "inline-flex min-h-12 items-center text-[1.0625rem] leading-[1.375rem] text-ink no-underline hover:underline hover:decoration-coral hover:decoration-2 hover:underline-offset-4 lg:min-h-10";

/**
 * The admin's 194/112 sentence with each emergency number as a tap-to-call
 * link inside it: same quiet line, underlined like any inline text link, no
 * buttons. Other text (and any other number) stays as written.
 */
export function EmergencyLine({ text }: { text: string }) {
  return (
    <p>
      {text.split(/(?<!\d)(194|112)(?!\d)/).map((part, index) =>
        index % 2 === 1 ? (
          <a key={index} href={`tel:${part}`} className="link-underline">
            {part}
          </a>
        ) : (
          part
        ),
      )}
    </p>
  );
}

export async function SiteFooter() {
  const settings = await fetchPublicSettings();

  return <SiteFooterContent settings={settings} />;
}

function Column({ title, children }: { title: string; children: ReactNode }) {
  return (
    <div>
      <h2 className="mb-1 text-[0.9375rem] font-semibold leading-[1.375rem] text-ink-2 lg:mb-2 lg:text-base">
        {title}
      </h2>
      <ul className="m-0 flex list-none flex-col p-0">{children}</ul>
    </div>
  );
}

/**
 * Sand footer with 28px top corners. Mobile: logo, a 2-column link grid
 * (48px rows), then the bottom lines. Desktop: a 12-column grid (5 brand +
 * 3 link columns) and a ruled bottom line. The bottom line carries the site's
 * only standing safety copy: ONE quiet 194/112 sentence and ONE disclaimer
 * (both admin-editable settings), in ink-2 — no red box, no repeats. The sand
 * runs down behind the mobile tab bar (bottom padding = --tabbar-space).
 */
export function SiteFooterContent({ settings }: { settings: PublicSettings }) {
  const year = new Date().getFullYear();

  return (
    <footer className="mt-10 rounded-t-[28px] bg-sand lg:mt-20">
      <div className="mx-auto w-full max-w-[1240px] px-5 pb-[calc(2.5rem+var(--tabbar-space))] pt-10 lg:px-6 lg:pt-14">
        <div className="grid grid-cols-2 gap-x-4 gap-y-6 lg:grid-cols-12 lg:gap-x-6">
          <div className="col-span-2 lg:col-span-5">
            <Link
              href="/"
              className="inline-flex min-h-12 items-center rounded-lg no-underline"
            >
              <SiteBrandMark logoUrl={settings.logo_url} />
              <span className="sr-only">, {t("nav.homeLink")}</span>
            </Link>
            <p className="mt-3 max-w-[420px] type-body text-ink">
              {t("footer.tagline")}
            </p>
          </div>

          <div className="lg:col-span-2">
            <Column title={t("footer.directory")}>
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
              {isPathEnabled("/pharmacies", settings) ? (
                <li>
                  <Link href="/pharmacies" className={linkClass}>
                    {t("nav.pharmacies")}
                  </Link>
                </li>
              ) : null}
              {isPathEnabled("/products", settings) ? (
                <li>
                  <Link href="/products" className={linkClass}>
                    {t("nav.products")}
                  </Link>
                </li>
              ) : null}
            </Column>
          </div>

          <div className="lg:col-span-2">
            <Column title={t("footer.resources")}>
              <li>
                <Link href="/about" className={linkClass}>
                  {t("footer.about")}
                </Link>
              </li>
              <li>
                <Link href="/transparency" className={linkClass}>
                  {t("integrity.footerLink")}
                </Link>
              </li>
              {isPathEnabled("/guidance", settings) ? (
                <li>
                  <Link href="/guidance" className={linkClass}>
                    {t("nav.guidance")}
                  </Link>
                </li>
              ) : null}
              {/* Only once a clinician-reviewed guide is published. */}
              {hasPublishedFirstAidGuides() ? (
                <li>
                  <Link href={FIRST_AID_BASE} className={linkClass}>
                    {FIRST_AID_COPY.title}
                  </Link>
                </li>
              ) : null}
              {isPathEnabled("/forum", settings) ? (
                <li>
                  <Link href="/forum" className={linkClass}>
                    {t("nav.forum")}
                  </Link>
                </li>
              ) : null}
              <li>
                <Link href="/community" className={linkClass}>
                  {t("levels.pageTitle")}
                </Link>
              </li>
              <li>
                <FooterSearchButton className={linkClass} />
              </li>
            </Column>
          </div>

          <div className="lg:col-span-3">
            <Column title={t("footer.legal")}>
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
            </Column>
          </div>
        </div>

        <div className="mt-8 flex flex-col gap-3 border-t border-line pt-6 lg:mt-10 lg:flex-row lg:items-start lg:justify-between lg:gap-10">
          <div className="flex max-w-[760px] flex-col gap-1 type-meta text-ink-2">
            <EmergencyLine text={settings.footer_emergency_text} />
            <p>
              {t("footer.informational")} {settings.footer_disclaimer_text}
            </p>
          </div>
          <p className="type-meta text-ink-2 lg:whitespace-nowrap">
            {tFormat("footer.copyright", {
              year,
              name: settings.copyright_name,
            })}
          </p>
        </div>
      </div>
    </footer>
  );
}
