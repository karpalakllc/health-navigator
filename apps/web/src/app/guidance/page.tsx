import type { Metadata } from "next";
import { GuidanceGuide } from "@/components/guidance/guidance-guide";
import { GuidanceSafetyNotice } from "@/components/guidance/guidance-safety-notice";
import { guidancePageClass } from "@/components/guidance/guidance-layout";
import { ComingSoonShell } from "@/components/layout/coming-soon-shell";
import { NoticeTelLink } from "@/components/ui/notice";
import { fetchGuidanceCatalog } from "@/lib/api/guidance-flow";
import type { GuidanceCatalog } from "@/lib/api/guidance-v2";
import { fetchPublicSettings } from "@/lib/api/settings";
import { isModuleOn } from "@/lib/api/public-settings";
import { pageMetadata } from "@/lib/metadata";
import { t } from "@/i18n/t";

export async function generateMetadata(): Promise<Metadata> {
  const settings = await fetchPublicSettings();

  return pageMetadata(t("guidance.title"), t("guidance.description"), {
    path: "/guidance",
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

  let catalog: GuidanceCatalog | null = null;

  try {
    catalog = await fetchGuidanceCatalog();
  } catch {
    catalog = null;
  }

  return (
    <div className={guidancePageClass}>
      {catalog === null || catalog.flows.length === 0 ? (
        // Fail closed: no published flow → no questionnaire, only the safe
        // fallback with the emergency numbers (docs/triage-safety.md).
        <div className="flex flex-col gap-6">
          <div className="flex flex-col gap-3">
            <h1 className="type-h1 text-ink">{t("guidance.title")}</h1>
            <p className="type-reading measure text-ink">
              {t("guidance.description")}
            </p>
          </div>
          <GuidanceSafetyNotice />
          <p className="type-reading text-ink">{t("guidance.unavailable")}</p>
          <p className="type-reading text-ink">
            {t("guidance.compactLead")} <NoticeTelLink number="194" />{" "}
            {t("guidance.compactOr")} <NoticeTelLink number="112" />.
          </p>
        </div>
      ) : (
        // The guide renders the page's h1 itself: the intro shows it in the
        // hero, later steps keep it for screen readers only.
        <GuidanceGuide
          catalog={catalog}
          pharmaciesOn={isModuleOn(settings, "public_pharmacies")}
        />
      )}
    </div>
  );
}
