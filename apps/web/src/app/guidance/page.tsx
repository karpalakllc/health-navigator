import type { Metadata } from "next";
import { GuidanceWizard } from "@/components/guidance/guidance-wizard";
import { GuidanceSafetyNotice } from "@/components/guidance/guidance-safety-notice";
import { guidancePageClass } from "@/components/guidance/guidance-layout";
import { ComingSoonShell } from "@/components/layout/coming-soon-shell";
import { fetchGuidanceFlow } from "@/lib/api/guidance-flow";
import { fetchPublicSettings } from "@/lib/api/settings";
import { isModuleOn } from "@/lib/api/public-settings";
import { pageMetadata } from "@/lib/metadata";
import { t } from "@/i18n/t";

export async function generateMetadata(): Promise<Metadata> {
  const settings = await fetchPublicSettings();

  return pageMetadata(t("guidance.title"), t("guidance.description"), {
    noIndex: !settings.public_guidance,
  });
}

export default async function GuidancePage() {
  const settings = await fetchPublicSettings();

  if (!isModuleOn(settings, "public_guidance")) {
    return (
      <ComingSoonShell
        title={t("guidance.title")}
        description={t("guidance.description")}
      />
    );
  }

  let flow = null;
  let unavailable = false;

  try {
    flow = await fetchGuidanceFlow();
  } catch {
    unavailable = true;
  }

  return (
    <div className={guidancePageClass}>
      {unavailable || !flow ? (
        <div className="flex flex-col gap-6">
          <div className="flex flex-col gap-3">
            <h1 className="type-h1 text-ink">{t("guidance.title")}</h1>
            <p className="type-reading measure text-ink">
              {t("guidance.description")}
            </p>
          </div>
          <GuidanceSafetyNotice />
          <p className="type-reading text-ink">{t("guidance.unavailable")}</p>
        </div>
      ) : (
        // The wizard renders the page's h1 itself: the intro shows it in the
        // hero, later steps keep it for screen readers only, so the emergency
        // screen has nothing above it but the header.
        <GuidanceWizard
          flow={flow}
          pharmaciesOn={isModuleOn(settings, "public_pharmacies")}
        />
      )}
    </div>
  );
}
