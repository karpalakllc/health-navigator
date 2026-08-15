import { LegalPage } from "@/components/legal/legal-page";
import { TermsContent, termsLastUpdated } from "@/content/legal/terms";
import { pageMetadata } from "@/lib/metadata";
import { t } from "@/i18n/t";

export const metadata = pageMetadata(t("legal.termsTitle"), undefined, {
  path: "/terms",
});

export default function TermsPage() {
  return (
    <LegalPage titleKey="legal.termsTitle" lastUpdated={termsLastUpdated}>
      <TermsContent />
    </LegalPage>
  );
}
