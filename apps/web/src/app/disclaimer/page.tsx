import { LegalPage } from "@/components/legal/legal-page";
import { DisclaimerContent, disclaimerLastUpdated } from "@/content/legal/disclaimer";

export default function DisclaimerPage() {
  return (
    <LegalPage titleKey="legal.disclaimerTitle" lastUpdated={disclaimerLastUpdated}>
      <DisclaimerContent />
    </LegalPage>
  );
}
