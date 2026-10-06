import { EmergencyCallLinks } from "@/components/guidance/emergency-call-links";
import { TextLink } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import { Icon, type IconName } from "@/components/ui/icons";
import { t, type MessageKey } from "@/i18n/t";

const POINTS: Array<{ icon: IconName; title: MessageKey; body: MessageKey }> = [
  {
    icon: "shield-check",
    title: "disclaimerPage.notDoctorTitle",
    body: "disclaimerPage.notDoctorBody",
  },
  {
    icon: "alert-triangle",
    title: "disclaimerPage.emergencyTitle",
    body: "disclaimerPage.emergencyBody",
  },
  {
    icon: "info",
    title: "disclaimerPage.illustrativeTitle",
    body: "disclaimerPage.illustrativeBody",
  },
];

export function DisclaimerPageContent() {
  return (
    <div className="mx-auto flex w-full max-w-[1240px] flex-col gap-8 px-5 pb-14 pt-6 lg:gap-14 lg:px-6 lg:pb-20 lg:pt-10">
      <header className="flex flex-col gap-3">
        <p className="type-meta font-semibold text-ink-2">
          {t("disclaimerPage.badge")}
        </p>
        <h1 className="type-h1 text-ink">{t("disclaimerPage.title")}</h1>
        <p className="measure type-reading text-ink">
          {t("disclaimerPage.intro")}
        </p>
      </header>

      <ul className="grid gap-4 lg:grid-cols-3 lg:gap-6">
        {POINTS.map((point) => (
          <Card as="li" key={point.title} className="flex flex-col gap-3">
            <span className="inline-flex size-12 items-center justify-center rounded-full bg-apricot text-ink">
              <Icon name={point.icon} size={24} />
            </span>
            <h2 className="type-h3 text-ink">{t(point.title)}</h2>
            <p className="type-reading text-ink">{t(point.body)}</p>
          </Card>
        ))}
      </ul>

      {/* The tel: links are the one place red belongs on this page. */}
      <Card
        as="section"
        aria-labelledby="disclaimer-emergency"
        className="flex flex-col gap-5 lg:flex-row lg:items-center lg:justify-between lg:p-10"
      >
        <div className="flex flex-col gap-1">
          <h2 id="disclaimer-emergency" className="type-h2 text-ink">
            {t("disclaimerPage.emergencyHeading")}
          </h2>
          <p className="type-body text-ink-2">
            {t("disclaimerPage.emergencySubheading")}
          </p>
        </div>
        <EmergencyCallLinks />
      </Card>

      <nav aria-labelledby="disclaimer-related" className="flex flex-col gap-1">
        <h2 id="disclaimer-related" className="type-label text-ink-2">
          {t("legal.related")}
        </h2>
        <ul className="flex flex-wrap gap-x-6">
          <li>
            <TextLink href="/privacy">{t("footer.privacy")}</TextLink>
          </li>
          <li>
            <TextLink href="/terms">{t("footer.terms")}</TextLink>
          </li>
          <li>
            <TextLink href="/about">{t("footer.about")}</TextLink>
          </li>
        </ul>
      </nav>
    </div>
  );
}
