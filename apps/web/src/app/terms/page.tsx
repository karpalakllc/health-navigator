import { LegalPage } from "@/components/legal/legal-page";
import { TermsContent, termsLastUpdated } from "@/content/legal/terms";

export default function TermsPage() {
  return (
    <LegalPage titleKey="legal.termsTitle" lastUpdated={termsLastUpdated}>
      <TermsContent />
    </LegalPage>
  );
}
