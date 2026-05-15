import type { Metadata } from "next";
import Link from "next/link";
import { DoctorCard } from "@/components/directory/doctor-card";
import { HomeHeroSearch } from "@/components/layout/home-hero-search";
import { HomeQuickActions } from "@/components/layout/home-quick-actions";
import { HomeTrustStrip } from "@/components/layout/home-trust-strip";
import { PageSection } from "@/components/ui/page-section";
import { PageShell } from "@/components/ui/page-shell";
import { fetchDoctors } from "@/lib/api/doctors";
import { pageMetadata } from "@/lib/metadata";
import { t } from "@/i18n/t";

export const metadata: Metadata = pageMetadata(
  `${t("home.title")} ${t("home.titleHighlight")}`.trim(),
  t("home.subtitle"),
);

export default async function Home() {
  let featured: Awaited<ReturnType<typeof fetchDoctors>>["data"] = [];

  try {
    const response = await fetchDoctors({ featured: true, per_page: 3 });
    featured = response.data;
  } catch {
    featured = [];
  }

  return (
    <PageShell gap="loose" className="pb-16">
      <section className="relative overflow-hidden rounded-3xl border border-border bg-gradient-to-br from-primary/5 via-accent/5 to-transparent px-6 py-12 sm:px-10">
        <div className="mx-auto max-w-3xl space-y-6 text-center">
          <h1 className="text-4xl font-bold tracking-tight sm:text-5xl">
            {t("home.title")}{" "}
            <span className="text-gradient">{t("home.titleHighlight")}</span>
          </h1>
          <p className="text-lg text-muted-foreground">{t("home.subtitle")}</p>
          <HomeHeroSearch />
        </div>
      </section>

      <HomeTrustStrip />

      <PageSection title={t("home.quickActions")}>
        <HomeQuickActions />
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
