import type { Metadata } from "next";
import { HeroMeshCard } from "@/components/design/hero-mesh-card";
import { SectionHeading } from "@/components/design/section-heading";
import { TrustRibbon } from "@/components/design/trust-ribbon";
import { HomeFacilitiesRail } from "@/components/layout/home-facilities-rail";
import { HomeFaq } from "@/components/layout/home-faq";
import { HomeFeaturedDoctorsSection } from "@/components/layout/home-featured-doctors-section";
import { HomeForumTransparency } from "@/components/layout/home-forum-transparency";
import { HomeHeroSearchClient } from "@/components/layout/home-hero-search-client";
import { HomeHowItWorksSection } from "@/components/layout/home-how-it-works-section";
import { HomeQuickActions } from "@/components/layout/home-quick-actions";
import { HomeSpecialtyExplorer } from "@/components/layout/home-specialty-explorer";
import { PageShell } from "@/components/ui/page-shell";
import { homeHeroCopyClass, pageEdgeBleedClass } from "@/components/ui/layout";
import type { DoctorListItem } from "@/lib/api/types";
import { fetchDoctors } from "@/lib/api/doctors";
import { fetchFacilities } from "@/lib/api/facilities";
import { fetchPublicSettings } from "@/lib/api/settings";
import { fetchSpecialties } from "@/lib/api/specialties";
import { pageMetadata } from "@/lib/metadata";
import { t } from "@/i18n/t";

export const metadata: Metadata = pageMetadata(
  `${t("home.heroTitleLead")} ${t("home.heroTitleAccent")}`.trim(),
  t("home.heroSubtitle"),
);

const TOP_RATED_MIN_REVIEWS = 2;

export default async function Home() {
  const settings = await fetchPublicSettings();
  let topDoctors: Awaited<ReturnType<typeof fetchDoctors>>["data"] = [];
  let facilities: Awaited<ReturnType<typeof fetchFacilities>>["data"] = [];
  let specialties: Awaited<ReturnType<typeof fetchSpecialties>> = [];

  try {
    const [topRes, featuredRes, facilitiesRes, specRes] = await Promise.all([
      fetchDoctors({
        sort: "rating",
        min_reviews: TOP_RATED_MIN_REVIEWS,
        per_page: 8,
      }),
      fetchDoctors({ featured: true, per_page: 8 }),
      fetchFacilities({ featured: true, per_page: 3 }),
      fetchSpecialties(),
    ]);
    topDoctors = mergeHomeDoctors(topRes.data, featuredRes.data, 8);
    facilities = facilitiesRes.data;
    specialties = specRes;
  } catch {
    topDoctors = [];
    facilities = [];
    specialties = [];
  }

  return (
    <>
      <div className={`${pageEdgeBleedClass} pb-2 pt-[18px]`}>
        <section className="space-y-4">
        <HeroMeshCard align="full">
          <div className="mx-auto inline-flex w-fit items-center gap-2.5 rounded-full border border-white/90 bg-white/90 px-4 py-2 text-sm font-semibold text-[#5c6670] shadow-[0_14px_40px_rgb(16_30_36_/_0.07)]">
            <ShieldIcon />
            {t("home.heroBadge")}
          </div>
          <h1
            className={`${homeHeroCopyClass} mt-[18px] text-[clamp(2.5rem,5vw,4.9rem)] font-black leading-[0.95] tracking-[-0.055em] text-foreground`}
          >
            {t("home.heroTitleLead")}{" "}
            <span className="text-primary">{t("home.heroTitleAccent")}</span>
          </h1>
          <p className={`${homeHeroCopyClass} mt-[22px] text-[1.14rem] leading-[1.8] text-muted-foreground`}>
            {t("home.heroSubtitle")}
          </p>
          <HomeHeroSearchClient />
        </HeroMeshCard>

        <TrustRibbon
          items={[
            { text: t("home.trustInformational"), tone: "teal", icon: <InfoIcon /> },
            { text: t("home.trustEmergency"), tone: "red", icon: <PhoneIcon /> },
            { text: t("home.trustModerated"), tone: "teal", icon: <CheckIcon /> },
            { text: t("home.trustLocal"), tone: "red", icon: <MapIcon /> },
          ]}
          className="hidden xl:grid"
        />
        <TrustRibbon
          columns={3}
          items={[
            { text: t("home.trustInformational"), tone: "teal", icon: <InfoIcon /> },
            { text: t("home.trustEmergency"), tone: "red", icon: <PhoneIcon /> },
            { text: t("home.trustModerated"), tone: "teal", icon: <CheckIcon /> },
          ]}
          className="xl:hidden"
        />
        </section>
      </div>

      <PageShell gap="loose" className="pb-20 pt-8">
      {/* Quick actions — 3 cards only (reference) */}
      <section>
        <SectionHeading
          eyebrow={t("home.quickActions")}
          eyebrowVariant="pill"
          title={t("home.quickActionsTitle")}
          description={t("home.quickActionsDescription")}
        />
        <HomeQuickActions showForum={settings.public_forum} />
      </section>

      <HomeFeaturedDoctorsSection doctors={topDoctors} />

      {/* Institutions (reference #institutions, alt surface) */}
      <HomeFacilitiesRail facilities={facilities} />

      {/* Specialties (reference) */}
      {specialties.some((s) => s.doctors_count > 0) ? (
        <section>
          <SectionHeading
            title={t("home.specialtiesTitle")}
            description={t("home.specialtiesDescription")}
          />
          <HomeSpecialtyExplorer specialties={specialties} />
        </section>
      ) : null}

      <HomeHowItWorksSection />

      {/* Forum + transparency (reference #forum) */}
      {settings.public_forum ? (
        <section id="forum">
          <HomeForumTransparency />
        </section>
      ) : null}

      {/* FAQ (reference) */}
      <HomeFaq />
      </PageShell>
    </>
  );
}

function mergeHomeDoctors(primary: DoctorListItem[], fallback: DoctorListItem[], limit: number) {
  const seen = new Set<string>();
  const merged: DoctorListItem[] = [];
  for (const doctor of [...primary, ...fallback]) {
    if (seen.has(doctor.slug)) {
      continue;
    }
    seen.add(doctor.slug);
    merged.push(doctor);
    if (merged.length >= limit) {
      break;
    }
  }
  return merged;
}

function ShieldIcon() {
  return (
    <svg className="h-4 w-4 text-accent" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" aria-hidden>
      <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" strokeLinecap="round" />
    </svg>
  );
}

function InfoIcon() {
  return (
    <svg className="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" aria-hidden>
      <circle cx="12" cy="12" r="9" />
      <path d="M12 10v6M12 7h.01" strokeLinecap="round" />
    </svg>
  );
}

function PhoneIcon() {
  return (
    <svg className="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" aria-hidden>
      <path d="M22 16.92v3a2 2 0 01-2.18 2 19.8 19.8 0 01-8.63-3.07 19.5 19.5 0 01-6-6A19.8 19.8 0 014.11 2h3a2 2 0 012 1.72c.383.12.762.26 1.128.4a2 2 0 012.11-.45l1.27-.27a2 2 0 012.53 1.01L22 16.92z" strokeLinecap="round" />
    </svg>
  );
}

function CheckIcon() {
  return (
    <svg className="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" aria-hidden>
      <path d="M9 12l2 2 4-4M21 12a9 9 0 11-18 0 9 9 0 0118 0z" strokeLinecap="round" />
    </svg>
  );
}

function MapIcon() {
  return (
    <svg className="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" aria-hidden>
      <path d="M12 21s-7-4-7-10a7 7 0 1114 0c0 6-7 10-7 10z" strokeLinecap="round" />
      <circle cx="12" cy="11" r="2.5" />
    </svg>
  );
}
