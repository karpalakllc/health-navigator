import { SectionHeading } from "@/components/design/section-heading";
import { HomeHowItWorks } from "@/components/layout/home-how-it-works";
import { t } from "@/i18n/t";

export function HomeHowItWorksSection() {
  return (
    <section id="guides">
      <div className="how-card rounded-[30px] p-7">
        <SectionHeading
          eyebrow={t("home.howItWorksEyebrow")}
          eyebrowVariant="plain-with-icon"
          iconTone="teal"
          title={t("home.howItWorksTitle")}
          description={t("home.howItWorksDescription")}
          className="mb-6"
          icon={
            <svg className="h-4 w-4 text-accent" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" aria-hidden>
              <circle cx="12" cy="12" r="9" />
              <ellipse cx="12" cy="12" rx="9" ry="3" />
            </svg>
          }
        />
        <HomeHowItWorks />
      </div>
    </section>
  );
}
