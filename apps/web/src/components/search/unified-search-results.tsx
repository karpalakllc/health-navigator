import type { ReactNode } from "react";
import { ResultsGrid } from "@/components/directory/results-grid";
import { DoctorCard } from "@/components/directory/doctor-card";
import { FacilityCard } from "@/components/directory/facility-card";
import { PharmacyCard } from "@/components/directory/pharmacy-card";
import { ProductCard } from "@/components/catalog/product-card";
import { OnDutySourceNote } from "@/components/urgent-care/on-duty-tag";
import { CommunityTopicCard } from "@/components/home/community-topic-card";
import { AdvancedSearchTrigger } from "@/components/search/advanced-search-trigger";
import { FuzzySearchNotice } from "@/components/search/fuzzy-search-notice";
import {
  SearchEmptyState,
  SearchErrorState,
} from "@/components/search/search-states";
import { ChipLink, RemovableChip } from "@/components/ui/chip";
import { SectionHeader } from "@/components/ui/section-header";
import { fetchUnifiedSearch } from "@/lib/api/search";
import type { UnifiedSearchResult } from "@/lib/api/types";
import { directorySearchHref } from "@/lib/search";
import { specialtyIcon } from "@/lib/specialty-icons";
import { t, tCount, tFormat } from "@/i18n/t";

type UnifiedSearchResultsProps = {
  /** Absent for a city-only browse (everything in that city). */
  q?: string;
  city?: string;
};

type Section = {
  id: string;
  title: string;
  total: number;
  viewAllHref: string;
  body: ReactNode;
};

/**
 * Results of one /search query, grouped by vertical (up to 5 per group, each
 * with „Види ги сите“ into the filtered list). Above them: the result count,
 * the applied city as a removable chip, one chip per non-empty group (jumps to
 * it) and the advanced search. Empty and failed searches get their own state.
 */
export async function UnifiedSearchResults({
  q,
  city,
}: UnifiedSearchResultsProps) {
  const cityParam = city?.trim() || undefined;

  let result: UnifiedSearchResult;
  try {
    result = await fetchUnifiedSearch({
      q,
      city: cityParam,
      // City-only browse has no relevance cut-off: show a fuller page.
      per_page: q ? 5 : 10,
    });
  } catch {
    return <SearchErrorState q={q} city={cityParam} />;
  }

  const { doctors, facilities, pharmacies, products, forum_topics } = result;
  // Matching specialties: shortcuts into the doctor list, same city.
  const specialties = result.specialties ?? [];

  const sections: Section[] = [
    {
      id: "search-doctors",
      title: t("search.sectionDoctors"),
      total: doctors.meta.total,
      viewAllHref: directorySearchHref("/doctors", q, cityParam),
      body: (
        <ResultsGrid>
          {doctors.data.map((doctor) => (
            <li key={doctor.slug}>
              <DoctorCard doctor={doctor} />
            </li>
          ))}
        </ResultsGrid>
      ),
    },
    {
      id: "search-facilities",
      title: t("search.sectionFacilities"),
      total: facilities.meta.total,
      viewAllHref: directorySearchHref("/facilities", q, cityParam),
      body: (
        <ResultsGrid>
          {facilities.data.map((facility) => (
            <li key={facility.slug}>
              <FacilityCard facility={facility} />
            </li>
          ))}
        </ResultsGrid>
      ),
    },
    {
      id: "search-pharmacies",
      title: t("search.sectionPharmacies"),
      total: pharmacies.meta.total,
      viewAllHref: directorySearchHref("/pharmacies", q, cityParam),
      body: (
        <>
          {/* ФЗОМ's caveat once for the group, not on every on-duty card. */}
          {pharmacies.data.some((pharmacy) => pharmacy.on_duty_today) ? (
            <OnDutySourceNote className="mb-4" />
          ) : null}
          <ResultsGrid>
            {pharmacies.data.map((pharmacy) => (
              <li key={pharmacy.slug}>
                <PharmacyCard pharmacy={pharmacy} />
              </li>
            ))}
          </ResultsGrid>
        </>
      ),
    },
    {
      id: "search-forum",
      title: t("search.sectionForum"),
      total: forum_topics.meta.total,
      viewAllHref: q ? `/forum?q=${encodeURIComponent(q)}` : "/forum",
      body: (
        <ul className="grid gap-3 lg:grid-cols-2 lg:gap-4">
          {forum_topics.data.map((topic) => (
            <li key={`${topic.category.slug}/${topic.slug}`}>
              <CommunityTopicCard topic={topic} showExcerpt />
            </li>
          ))}
        </ul>
      ),
    },
    {
      id: "search-products",
      title: t("search.sectionProducts"),
      total: products.meta.total,
      viewAllHref: directorySearchHref("/products", q, cityParam),
      body: (
        <ResultsGrid>
          {products.data.map((product) => (
            <li key={product.slug}>
              <ProductCard product={product} />
            </li>
          ))}
        </ResultsGrid>
      ),
    },
  ].filter((section) => section.total > 0);

  // Nothing matched exactly: these are close names („domenika“ → Доминика).
  const fuzzy = Boolean(q && result.fuzzy && sections.length > 0);

  return (
    <div className="flex flex-col gap-10 lg:gap-14">
      <div className="flex flex-col gap-4">
        {fuzzy && q ? (
          <FuzzySearchNotice
            q={q}
            suggestion={result.suggestion}
            suggestionHref={
              result.suggestion
                ? directorySearchHref("/search", result.suggestion, cityParam)
                : undefined
            }
          />
        ) : null}
        <div className="flex flex-wrap items-center justify-between gap-3">
          {sections.length > 0 ? (
            <p className="type-body font-semibold text-ink">
              {tCount("search.resultsSummary", result.grand_total)}
            </p>
          ) : null}
          <AdvancedSearchTrigger />
        </div>

        {cityParam || sections.length > 1 ? (
          <nav aria-label={t("search.sectionsNav")}>
            <ul className="scroll-row -mx-5 -my-2.5 flex gap-2 overflow-x-auto px-5 py-2.5 lg:mx-0 lg:flex-wrap lg:px-0">
              {cityParam ? (
                <li className="flex-none">
                  <RemovableChip
                    label={tFormat("search.cityFilter", { city: cityParam })}
                    removeHref={directorySearchHref("/search", q)}
                  />
                </li>
              ) : null}
              {sections.length > 1
                ? sections.map((section) => (
                    <li key={section.id} className="flex-none">
                      <ChipLink href={`#${section.id}`}>
                        {section.title}
                        <span className="ml-1.5 text-ink-2">
                          {section.total}
                        </span>
                      </ChipLink>
                    </li>
                  ))
                : null}
            </ul>
          </nav>
        ) : null}

        {specialties.length > 0 ? (
          <nav aria-label={t("homeSearch.resultsSpecialties")}>
            <p className="type-meta mb-2 font-semibold text-ink">
              {t("homeSearch.resultsSpecialties")}
            </p>
            <ul className="scroll-row -mx-5 -my-2.5 flex gap-2 overflow-x-auto px-5 py-2.5 lg:mx-0 lg:flex-wrap lg:px-0">
              {specialties.map((specialty) => (
                <li key={specialty.slug} className="flex-none">
                  <ChipLink
                    href={directorySearchHref(
                      "/doctors",
                      undefined,
                      cityParam,
                      {
                        specialty: specialty.slug,
                      },
                    )}
                    icon={specialtyIcon(specialty)}
                  >
                    {specialty.name}
                    <span className="ml-1.5 text-ink-2">
                      {specialty.doctors_count}
                    </span>
                  </ChipLink>
                </li>
              ))}
            </ul>
          </nav>
        ) : null}

        {sections.length > 0 ? (
          <p className="type-meta text-ink-2">{t("search.unifiedHint")}</p>
        ) : null}
      </div>

      {sections.length === 0 ? (
        <SearchEmptyState q={q} city={cityParam} />
      ) : (
        sections.map((section) => (
          <section
            key={section.id}
            id={section.id}
            aria-labelledby={`${section.id}-title`}
            className="flex flex-col gap-4"
          >
            <SectionHeader
              id={`${section.id}-title`}
              title={section.title}
              description={tCount("search.resultsTotalLine", section.total)}
              action={{
                href: section.viewAllHref,
                label: (
                  <>
                    {t("search.viewAllInSection")}
                    <span className="sr-only">: {section.title}</span>
                  </>
                ),
              }}
            />
            {section.body}
          </section>
        ))
      )}
    </div>
  );
}
