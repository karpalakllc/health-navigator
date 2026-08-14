import Link from "next/link";
import { DirectoryHero } from "@/components/design/directory-hero";
import { ProfileContentCard } from "@/components/design/profile-content-card";
import { TrustRibbon } from "@/components/design/trust-ribbon";
import { PageShell } from "@/components/ui/page-shell";
import { PageHeroBleed } from "@/components/design/page-hero-bleed";
import { pageMetadata } from "@/lib/metadata";
import { t } from "@/i18n/t";
import type { Metadata } from "next";

export const metadata: Metadata = pageMetadata(
  t("about.title"),
  t("about.description"),
);

export default function AboutPage() {
  const values = [
    t("about.valueTrust"),
    t("about.valueClarity"),
    t("about.valueCommunity"),
    t("about.valueAccess"),
  ];

  return (
    <>
      <PageHeroBleed>
        <DirectoryHero
          badge={t("about.title")}
          title={t("about.missionTitle")}
          description={t("about.missionBody")}
        />
        <TrustRibbon
          variant="compact"
          columns={3}
          items={[
            {
              text: t("home.trustModerated"),
              icon: <ShieldIcon />,
              tone: "teal",
            },
            {
              text: t("home.trustInformational"),
              icon: <InfoIcon />,
              tone: "red",
            },
            { text: t("home.trustLocal"), icon: <MapIcon />, tone: "teal" },
          ]}
        />
      </PageHeroBleed>

      <PageShell className="gap-8 pb-16">
        <div className="grid gap-6 lg:grid-cols-2">
          <ProfileContentCard title={t("about.goalTitle")}>
            <p className="text-sm leading-relaxed text-muted-foreground">
              {t("about.goalBody")}
            </p>
          </ProfileContentCard>
          <ProfileContentCard title={t("about.valuesTitle")}>
            <ul className="space-y-2 text-sm text-muted-foreground">
              {values.map((item) => (
                <li key={item} className="flex gap-2">
                  <span className="font-bold text-primary" aria-hidden>
                    ✓
                  </span>
                  <span>{item}</span>
                </li>
              ))}
            </ul>
          </ProfileContentCard>
        </div>

        <ProfileContentCard title={t("about.howTitle")}>
          <p className="text-sm leading-relaxed text-muted-foreground">
            {t("about.howBody")}
          </p>
        </ProfileContentCard>

        <ProfileContentCard title={t("about.teamTitle")}>
          <p className="text-sm leading-relaxed text-muted-foreground">
            {t("about.teamBody")}
          </p>
        </ProfileContentCard>

        <ProfileContentCard title={t("about.ctaTitle")}>
          <p className="text-sm leading-relaxed text-muted-foreground">
            {t("about.ctaBody")}
          </p>
          <Link
            href="/disclaimer"
            className="mt-5 inline-flex min-h-[44px] items-center justify-center rounded-xl bg-primary px-6 text-sm font-extrabold text-primary-foreground hover:bg-primary/90"
          >
            {t("legal.disclaimerTitle")}
          </Link>
        </ProfileContentCard>
      </PageShell>
    </>
  );
}

function ShieldIcon() {
  return (
    <svg
      className="h-5 w-5"
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      strokeWidth="2"
      aria-hidden
    >
      <path
        d="M12 3l8 4v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V7l8-4z"
        strokeLinejoin="round"
      />
    </svg>
  );
}

function InfoIcon() {
  return (
    <svg
      className="h-5 w-5"
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      strokeWidth="2"
      aria-hidden
    >
      <circle cx="12" cy="12" r="9" />
      <path d="M12 10v6M12 7h.01" strokeLinecap="round" />
    </svg>
  );
}

function MapIcon() {
  return (
    <svg
      className="h-5 w-5"
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      strokeWidth="2"
      aria-hidden
    >
      <path d="M12 21s7-4.5 7-11a7 7 0 10-14 0c0 6.5 7 11 7 11z" />
      <circle cx="12" cy="10" r="2.5" />
    </svg>
  );
}
