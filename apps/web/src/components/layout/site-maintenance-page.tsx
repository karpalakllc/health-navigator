import { SiteBrandMark } from "@/components/layout/site-brand-mark";
import { StatusPanel } from "@/components/system/status-panel";
import { EmergencyPill } from "@/components/ui/emergency-pill";
import { t } from "@/i18n/t";

/**
 * Replaces the whole site (header and footer included) while maintenance is
 * on, so it brings its own slim header: the logo and the „Итно 194“ pill —
 * the emergency number must stay one tap away even now.
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
        <EmergencyPill />
      </header>
      <main id="main" className="flex-1">
        <StatusPanel
          icon="wrench"
          eyebrow={t("maintenance.badge")}
          title={t("maintenance.title")}
          description={message?.trim() || t("maintenance.defaultMessage")}
          footer={
            <p className="type-body text-ink-2">{t("maintenance.backSoon")}</p>
          }
        />
      </main>
    </>
  );
}
