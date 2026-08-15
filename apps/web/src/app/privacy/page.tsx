import { LegalPage } from "@/components/legal/legal-page";
import { PrivacyContent, privacyLastUpdated } from "@/content/legal/privacy";
import { pageMetadata } from "@/lib/metadata";
import { t } from "@/i18n/t";

export const metadata = pageMetadata(t("legal.privacyTitle"), undefined, {
  path: "/privacy",
});

export default function PrivacyPage() {
  return (
    <LegalPage titleKey="legal.privacyTitle" lastUpdated={privacyLastUpdated}>
      <PrivacyContent />
    </LegalPage>
  );
}
