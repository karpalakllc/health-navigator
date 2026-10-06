import type { ReactNode } from "react";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import { Icon, type IconName } from "@/components/ui/icons";
import { pageMetadata } from "@/lib/metadata";
import { t, type MessageKey } from "@/i18n/t";
import type { Metadata } from "next";

export const metadata: Metadata = pageMetadata(
  t("about.title"),
  t("about.description"),
  { path: "/about" },
);

const TRUST: Array<{ icon: IconName; key: MessageKey }> = [
  { icon: "shield-check", key: "home.trustModerated" },
  { icon: "info", key: "home.trustInformational" },
  { icon: "map-pin", key: "home.trustLocal" },
];

const VALUES: MessageKey[] = [
  "about.valueTrust",
  "about.valueClarity",
  "about.valueCommunity",
  "about.valueAccess",
];

export default function AboutPage() {
  return (
    <div className="mx-auto flex w-full max-w-[1240px] flex-col gap-10 px-5 pb-14 pt-4 lg:gap-14 lg:px-6 lg:pb-20 lg:pt-10">
      <section
        aria-labelledby="about-title"
        className="grid gap-8 rounded-sheet bg-apricot p-6 lg:grid-cols-[minmax(0,7fr)_minmax(0,5fr)] lg:items-center lg:gap-14 lg:p-14"
      >
        <div className="flex flex-col gap-3">
          <p className="type-meta font-semibold text-ink">{t("about.title")}</p>
          <h1 id="about-title" className="type-h1 text-ink">
            {t("about.missionTitle")}
          </h1>
          <p className="measure type-reading text-ink">
            {t("about.missionBody")}
          </p>
        </div>
        <ul className="flex flex-col gap-4">
          {TRUST.map((item) => (
            <li key={item.key} className="flex items-center gap-3">
              <span className="inline-flex size-12 shrink-0 items-center justify-center rounded-full bg-white text-ink">
                <Icon name={item.icon} size={24} />
              </span>
              <span className="type-body text-ink">{t(item.key)}</span>
            </li>
          ))}
        </ul>
      </section>

      <div className="grid gap-4 lg:grid-cols-2 lg:gap-6">
        <AboutCard id="about-goal" title={t("about.goalTitle")}>
          <p className="type-reading text-ink">{t("about.goalBody")}</p>
        </AboutCard>
        <AboutCard id="about-values" title={t("about.valuesTitle")}>
          <ul className="flex flex-col gap-3">
            {VALUES.map((key) => (
              <li key={key} className="flex items-start gap-3">
                <span className="mt-0.5 inline-flex size-7 shrink-0 items-center justify-center rounded-full bg-care-tint text-care">
                  <Icon name="check" size={18} />
                </span>
                <span className="type-body text-ink">{t(key)}</span>
              </li>
            ))}
          </ul>
        </AboutCard>
        <AboutCard id="about-how" title={t("about.howTitle")}>
          <p className="type-reading text-ink">{t("about.howBody")}</p>
        </AboutCard>
        <AboutCard id="about-team" title={t("about.teamTitle")}>
          <p className="type-reading text-ink">{t("about.teamBody")}</p>
        </AboutCard>
      </div>

      <Card
        as="section"
        aria-labelledby="about-cta"
        className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between lg:gap-10 lg:p-10"
      >
        <div className="flex flex-col gap-2">
          <h2 id="about-cta" className="type-h2 text-ink">
            {t("about.ctaTitle")}
          </h2>
          <p className="measure type-body text-ink-2">{t("about.ctaBody")}</p>
        </div>
        <Button
          href="/disclaimer"
          trailingIcon="arrow-right"
          // The long label wraps on a phone instead of spilling out of the
          // pill (it overflowed 390 by 13px).
          className="w-full min-w-0 whitespace-normal py-3 text-center lg:w-auto lg:shrink-0"
        >
          {t("legal.disclaimerTitle")}
        </Button>
      </Card>
    </div>
  );
}

function AboutCard({
  id,
  title,
  children,
}: {
  id: string;
  title: string;
  children: ReactNode;
}) {
  return (
    <Card as="section" aria-labelledby={id} className="flex flex-col gap-3">
      <h2 id={id} className="type-h3 text-ink">
        {title}
      </h2>
      {children}
    </Card>
  );
}
