import type { ReactNode } from "react";
import { DirectoryCardGrid } from "@/components/directory/directory-card-grid";
import { DoctorCard } from "@/components/directory/doctor-card";
import { FacilityCard } from "@/components/directory/facility-card";
import { PharmacyCard } from "@/components/directory/pharmacy-card";
import { ProductCard } from "@/components/catalog/product-card";
import { ForumSearchTopicCard } from "@/components/forum/forum-search-topic-card";
import { Button } from "@/components/ui/button";
import { fetchUnifiedSearch } from "@/lib/api/search";
import { directorySearchHref } from "@/lib/search";
import { t, tFormat } from "@/i18n/t";

type UnifiedSearchResultsProps = {
  q: string;
  city?: string;
};

export async function UnifiedSearchResults({ q, city }: UnifiedSearchResultsProps) {
  const cityParam = city?.trim() || undefined;

  const result = await fetchUnifiedSearch({
    q,
    city: cityParam,
    per_page: 5,
  });

  const { doctors, facilities, pharmacies, products, forum_topics } = result;

  return (
    <div className="space-y-10">
      <p className="text-sm text-muted-foreground">{t("search.unifiedHint")}</p>

      {result.grand_total === 0 ? (
        <p className="rounded-2xl border border-border bg-card p-6 text-center text-muted-foreground">
          {t("common.noResults")}
        </p>
      ) : null}

      <SearchSection
        title={t("search.sectionDoctors")}
        total={doctors.meta.total}
        viewAllHref={directorySearchHref("/doctors", q, cityParam)}
      >
        {doctors.data.length === 0 ? null : (
          <DirectoryCardGrid>
            {doctors.data.map((doctor) => (
              <li key={doctor.slug}>
                <DoctorCard doctor={doctor} />
              </li>
            ))}
          </DirectoryCardGrid>
        )}
      </SearchSection>

      <SearchSection
        title={t("search.sectionFacilities")}
        total={facilities.meta.total}
        viewAllHref={directorySearchHref("/facilities", q, cityParam)}
      >
        {facilities.data.length === 0 ? null : (
          <DirectoryCardGrid>
            {facilities.data.map((facility) => (
              <li key={facility.slug}>
                <FacilityCard facility={facility} />
              </li>
            ))}
          </DirectoryCardGrid>
        )}
      </SearchSection>

      <SearchSection
        title={t("search.sectionPharmacies")}
        total={pharmacies.meta.total}
        viewAllHref={directorySearchHref("/pharmacies", q, cityParam)}
      >
        {pharmacies.data.length === 0 ? null : (
          <DirectoryCardGrid>
            {pharmacies.data.map((pharmacy) => (
              <li key={pharmacy.slug}>
                <PharmacyCard pharmacy={pharmacy} />
              </li>
            ))}
          </DirectoryCardGrid>
        )}
      </SearchSection>

      <SearchSection
        title={t("search.sectionForum")}
        total={forum_topics.meta.total}
        viewAllHref={`/search?q=${encodeURIComponent(q)}`}
      >
        {forum_topics.data.length === 0 ? null : (
          <ul className="grid gap-3">
            {forum_topics.data.map((topic) => (
              <li key={`${topic.category.slug}-${topic.slug}`}>
                <ForumSearchTopicCard topic={topic} />
              </li>
            ))}
          </ul>
        )}
      </SearchSection>

      <SearchSection
        title={t("search.sectionProducts")}
        total={products.meta.total}
        viewAllHref={directorySearchHref("/products", q, cityParam)}
      >
        {products.data.length === 0 ? null : (
          <DirectoryCardGrid>
            {products.data.map((product) => (
              <li key={product.slug}>
                <ProductCard product={product} />
              </li>
            ))}
          </DirectoryCardGrid>
        )}
      </SearchSection>
    </div>
  );
}

function SearchSection({
  title,
  total,
  viewAllHref,
  children,
}: {
  title: string;
  total: number;
  viewAllHref: string;
  children: ReactNode;
}) {
  if (total === 0) {
    return null;
  }

  return (
    <section className="space-y-4">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h2 className="text-lg font-semibold text-foreground">{title}</h2>
          <p className="text-sm text-muted-foreground">
            {tFormat("search.resultsTotalLine", { total: String(total) })}
          </p>
        </div>
        <Button href={viewAllHref} variant="outline" className="shrink-0 text-sm">
          {t("search.viewAllInSection")}
        </Button>
      </div>
      {children}
    </section>
  );
}
