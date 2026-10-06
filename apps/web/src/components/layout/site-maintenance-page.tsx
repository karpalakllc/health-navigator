import { SiteBrandMark } from "@/components/layout/site-brand-mark";
import { StatusPanel } from "@/components/system/status-panel";
import { NoticeTelLink } from "@/components/ui/notice";
import { t } from "@/i18n/t";

/**
 * Replaces the whole site (header and footer included) while maintenance is
 * on, so it brings its own slim header (the logo) and repeats the footer's
 * one quiet 194/112 line, since the footer is not rendered.
 */
export function SiteMaintenancePage({
  message,
  logoUrl,
}: {
  message: string | null;
  logoUrl?: string | null;
}) {
  return (
    <>
      <header className="mx-auto flex w-full max-w-[1240px] items-center justify-between gap-3 px-5 py-3 lg:px-6 lg:py-5">
        <SiteBrandMark logoUrl={logoUrl} />
      </header>
      <main id="main" className="flex-1">
        <StatusPanel
          icon="wrench"
          eyebrow={t("maintenance.badge")}
          title={t("maintenance.title")}
          description={message?.trim() || t("maintenance.defaultMessage")}
          footer={
            <>
              <p className="type-body text-ink-2">
                {t("maintenance.backSoon")}
              </p>
              <p className="mt-2 type-meta text-ink-2">
                {t("footer.emergency")} <NoticeTelLink number="194" />{" "}
                {t("footer.emergencyOr")} <NoticeTelLink number="112" />{" "}
                {t("footer.emergencyEnd")}
              </p>
            </>
          }
        />
      </main>
    </>
  );
}
