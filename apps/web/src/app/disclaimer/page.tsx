import type { Metadata } from "next";
import { LegalPage } from "@/components/legal/legal-page";
import {
  DisclaimerContent,
  disclaimerLastUpdated,
  disclaimerSections,
} from "@/content/legal/disclaimer";
import { pageMetadata } from "@/lib/metadata";
import { t } from "@/i18n/t";

export const metadata: Metadata = pageMetadata(
  t("legal.disclaimerTitle"),
  t("disclaimerPage.intro"),
  { path: "/disclaimer" },
);

export default function DisclaimerPage() {
  return (
    <LegalPage
      titleKey="legal.disclaimerTitle"
      lastUpdated={disclaimerLastUpdated}
      sections={disclaimerSections}
    >
      <DisclaimerContent />
    </LegalPage>
  );
}
