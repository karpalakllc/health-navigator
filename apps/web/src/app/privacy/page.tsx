import { LegalPage } from "@/components/legal/legal-page";
import { PrivacyContent, privacyLastUpdated } from "@/content/legal/privacy";

export default function PrivacyPage() {
  return (
    <LegalPage titleKey="legal.privacyTitle" lastUpdated={privacyLastUpdated}>
      <PrivacyContent />
    </LegalPage>
  );
}
