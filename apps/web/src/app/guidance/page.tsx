import type { Metadata } from "next";
import { GuidanceWizard } from "@/components/guidance/guidance-wizard";
import { GuidanceSafetyNotice } from "@/components/guidance/guidance-safety-notice";
import { PageHeader } from "@/components/directory/page-header";
import { PageShell } from "@/components/ui/page-shell";
import { ComingSoonShell } from "@/components/layout/coming-soon-shell";
import { fetchGuidanceFlow } from "@/lib/api/guidance";
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
    <PageShell>
      <PageHeader
        title={t("guidance.title")}
        description={t("guidance.description")}
      />

      {unavailable || !flow ? (
        <div className="space-y-4">
          <GuidanceSafetyNotice />
          <p className="text-sm text-muted-foreground">
            {t("guidance.unavailable")}
          </p>
        </div>
      ) : (
        <div className="rounded-2xl border border-border bg-card/70 p-6 shadow-sm md:p-8">
          <GuidanceWizard flow={flow} />
        </div>
      )}
    </PageShell>
  );
}
