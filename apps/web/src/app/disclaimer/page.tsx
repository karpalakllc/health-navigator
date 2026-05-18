import type { Metadata } from "next";
import { DisclaimerPageContent } from "@/components/legal/disclaimer-page-content";
import { pageMetadata } from "@/lib/metadata";
import { t } from "@/i18n/t";

export const metadata: Metadata = pageMetadata(
  t("legal.disclaimerTitle"),
  t("disclaimerPage.intro"),
);

export default function DisclaimerPage() {
  return <DisclaimerPageContent />;
}
