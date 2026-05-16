import type { Metadata } from "next";
import Link from "next/link";
import { DoctorCard } from "@/components/directory/doctor-card";
import { HomeClinicalTeaser } from "@/components/layout/home-clinical-teaser";
import { HomeCommunityTeaser } from "@/components/layout/home-community-teaser";
import { HomeHeroSearchClient } from "@/components/layout/home-hero-search-client";
import { HomeProductsRail } from "@/components/layout/home-products-rail";
import { HomeDoctorsRail } from "@/components/layout/home-doctors-rail";
import { HomeHowItWorks } from "@/components/layout/home-how-it-works";
import { HomeQuickActions } from "@/components/layout/home-quick-actions";
import { HomeSpecialtyExplorer } from "@/components/layout/home-specialty-explorer";
import { HomeTrustStrip } from "@/components/layout/home-trust-strip";
import { PageSection } from "@/components/ui/page-section";
import { PageShell } from "@/components/ui/page-shell";
import { fetchDoctors } from "@/lib/api/doctors";
import { fetchProducts } from "@/lib/api/products";
import { fetchPublicSettings } from "@/lib/api/settings";
import { fetchSpecialties } from "@/lib/api/specialties";
import { pageMetadata } from "@/lib/metadata";
import { t } from "@/i18n/t";

export const metadata: Metadata = pageMetadata(
  `${t("home.heroTitleLead")} ${t("home.heroTitleAccent")}`.trim(),
  t("home.heroSubtitle"),
);

const TOP_RATED_MIN_REVIEWS = 2;
const TOP_RATED_PER_PAGE = 6;

export default async function Home() {
  const settings = await fetchPublicSettings();
  let featured: Awaited<ReturnType<typeof fetchDoctors>>["data"] = [];
  let topRated: Awaited<ReturnType<typeof fetchDoctors>>["data"] = [];
  let specialties: Awaited<ReturnType<typeof fetchSpecialties>> = [];
  let catalogProducts: Awaited<ReturnType<typeof fetchProducts>>["data"] = [];

  try {
    const [featuredRes, topRes, specRes, productsRes] = await Promise.all([
      fetchDoctors({ featured: true, per_page: 3 }),
      fetchDoctors({
        sort: "rating",
        min_reviews: TOP_RATED_MIN_REVIEWS,
        per_page: TOP_RATED_PER_PAGE,
      }),
      fetchSpecialties(),
      settings.public_products
        ? fetchProducts({ per_page: 12 })
        : Promise.resolve({ data: [], meta: { total: 0 } }),
    ]);
    featured = featuredRes.data;
    topRated = topRes.data;
    specialties = specRes;
    catalogProducts = productsRes.data;
  } catch {
    featured = [];
    topRated = [];
    specialties = [];
    catalogProducts = [];
  }

  return (
    <PageShell gap="loose" className="pb-16">
      <section className="hero-shell relative overflow-hidden rounded-3xl border border-border bg-gradient-to-br from-primary/5 via-accent/5 to-transparent px-6 py-12 sm:px-10">
        <div className="mx-auto max-w-3xl space-y-6 text-center motion-safe:animate-fade-up">
          <p className="mx-auto inline-flex items-center gap-2 rounded-full border border-border/80 bg-card/70 px-4 py-1.5 text-xs font-medium text-muted-foreground shadow-sm backdrop-blur-sm">
            <span className="text-base leading-none" aria-hidden>
              🇲🇰
            </span>
            {t("home.heroBadge")}
          </p>
          <h1 className="text-balance text-4xl font-bold tracking-tight sm:text-5xl">
            {t("home.heroTitleLead")}{" "}
            <span className="text-gradient">{t("home.heroTitleAccent")}</span>
          </h1>
          <p className="text-pretty text-lg text-muted-foreground">{t("home.heroSubtitle")}</p>
          <HomeHeroSearchClient />
        </div>
      </section>

      <HomeTrustStrip />

      {settings.public_products ? <HomeProductsRail products={catalogProducts} /> : null}

      <PageSection title={t("home.quickActions")}>
        <HomeQuickActions
          showPharmacies={settings.public_pharmacies}
          showProducts={settings.public_products}
          showGuidance={settings.public_guidance}
          showForum={settings.public_forum}
        />
      </PageSection>

      {topRated.length > 0 ? (
        <HomeDoctorsRail
          doctors={topRated}
          title={t("home.topRatedTitle")}
          description={t("home.topRatedDescription")}
          viewAllHref="/doctors?sort=rating"
          viewAllLabel={t("home.topRatedViewAll")}
          disclaimer={t("home.topRatedDisclaimer")}
        />
      ) : null}

      {specialties.some((s) => s.doctors_count > 0) ? (
        <PageSection title={t("home.specialtiesTitle")} description={t("home.specialtiesDescription")}>
          <HomeSpecialtyExplorer specialties={specialties} />
        </PageSection>
      ) : null}

      <PageSection title={t("home.clinicalTeasersTitle")} description={t("home.clinicalTeasersDescription")}>
        <HomeClinicalTeaser />
      </PageSection>

      <PageSection title={t("home.communityTitle")} description={t("home.communityDescription")} variant="panel">
        <HomeCommunityTeaser />
      </PageSection>

      <PageSection title={t("home.howItWorksTitle")} description={t("home.howItWorksDescription")}>
        <HomeHowItWorks />
      </PageSection>

      {featured.length > 0 ? (
        <PageSection
          title={t("home.featuredDoctors")}
          description={t("home.featuredDoctorsDesc")}
          actions={
            <Link href="/doctors" className="text-sm font-medium text-primary hover:underline">
              {t("doctors.title")} →
            </Link>
          }
        >
          <ul className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            {featured.map((doctor) => (
              <li key={doctor.slug}>
                <DoctorCard doctor={doctor} />
              </li>
            ))}
          </ul>
        </PageSection>
      ) : null}
    </PageShell>
  );
}
