import type { Metadata } from "next";
import type { ReactNode } from "react";
import {
  HomeDirectoryTiles,
  type HomeTile,
} from "@/components/home/home-directory-tiles";
import { AdvancedSearchTrigger } from "@/components/search/advanced-search-trigger";
import { SearchQueryForm } from "@/components/search/search-query-form";
import { enabledDirectorySections } from "@/components/search/search-states";
import { UnifiedSearchResults } from "@/components/search/unified-search-results";
import type { IconName } from "@/components/ui/icons";
import { directorySearchHref, normalizeSearchQuery } from "@/lib/search";
import { pageMetadata } from "@/lib/metadata";
import { t, tFormat } from "@/i18n/t";

export const metadata: Metadata = pageMetadata(
  t("search.title"),
  t("search.description"),
  { path: "/search" },
);

type SearchPageProps = {
  searchParams: Promise<{
    q?: string;
    city?: string;
  }>;
};

const SECTION_ICONS: Record<string, IconName> = {
  "/doctors": "stethoscope",
  "/facilities": "building",
  "/pharmacies": "pill",
  "/products": "package",
};

export default async function SearchPage({ searchParams }: SearchPageProps) {
  const params = await searchParams;
  const qRaw = params.q ?? "";
  const qNormalized = normalizeSearchQuery(qRaw);
  const cityTrim = params.city?.trim();
  const city = cityTrim || undefined;

  if (qNormalized) {
    return (
      <SearchLayout
        eyebrow={t("search.directoryBadge")}
        title={tFormat("search.resultsFor", { q: qRaw.trim() })}
        form={<SearchQueryForm q={qRaw} city={params.city} />}
      >
        <UnifiedSearchResults q={qNormalized} city={city} />
      </SearchLayout>
    );
  }

  const sections = await enabledDirectorySections();
  const tiles: HomeTile[] = sections.map((section, index) => ({
    href: directorySearchHref(section.basePath, params.q, params.city),
    label: t(section.titleKey),
    icon: SECTION_ICONS[section.basePath] ?? "search",
    sub: t(section.descKey),
    feature: index === 0,
  }));

  return (
    <SearchLayout
      title={t("search.title")}
      intro={t("search.hubIntro")}
      form={<SearchQueryForm q={params.q} city={params.city} autoFocus />}
    >
      <div className="flex flex-col gap-6">
        <HomeDirectoryTiles
          tiles={tiles}
          title={t("search.hubDirectories")}
          headingId="search-directories-title"
        />
        <div>
          <AdvancedSearchTrigger />
        </div>
      </div>
    </SearchLayout>
  );
}

/** Apricot band with the h1 and the always-visible query form, then content. */
function SearchLayout({
  eyebrow,
  title,
  intro,
  form,
  children,
}: {
  eyebrow?: string;
  title: string;
  intro?: string;
  form: ReactNode;
  children: ReactNode;
}) {
  return (
    <div className="mx-auto w-full max-w-[1240px] pb-14 lg:px-6 lg:pb-20">
      <section
        aria-labelledby="search-title"
        className="mx-3 mt-1 rounded-sheet bg-apricot px-5 pb-5 pt-7 lg:mx-0 lg:mt-6 lg:px-14 lg:pb-10 lg:pt-12"
      >
        {eyebrow ? (
          <p className="mb-2 type-meta font-semibold text-ink">{eyebrow}</p>
        ) : null}
        <h1 id="search-title" className="type-h1 break-words text-ink">
          {title}
        </h1>
        {intro ? (
          <p className="type-body measure mt-3 text-ink">{intro}</p>
        ) : null}
        <div className="mt-5 lg:mt-8">{form}</div>
      </section>
      <div className="mt-8 px-5 lg:mt-14 lg:px-0">{children}</div>
    </div>
  );
}
