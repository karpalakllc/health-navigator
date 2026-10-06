import type { Metadata } from "next";
import { HomeCities } from "@/components/home/home-cities";
import { HomeCommunity, HomeTrustRow } from "@/components/home/home-community";
import { HomeDirectoryTiles } from "@/components/home/home-directory-tiles";
import { HomeFeaturedDoctorsRail } from "@/components/home/home-featured-doctors";
import { HomeGuidanceCard } from "@/components/home/home-guidance-card";
import { HomeHero } from "@/components/home/home-hero";
import { HomeRecentReviews } from "@/components/home/home-recent-reviews";
import { HomeRecentlyViewed } from "@/components/home/home-recently-viewed";
import { HomeSpecialties } from "@/components/home/home-specialties";
import { buildHeroStats, buildHomeTiles } from "@/components/home/home-tiles";
import { HomeForumTransparency } from "@/components/layout/home-forum-transparency";
import { HomeHowItWorksSection } from "@/components/layout/home-how-it-works-section";
import type { ForumCategory, ForumTopicSearchItem } from "@/lib/api/forum";
import { fetchForumCategories, fetchForumRecentTopics } from "@/lib/api/forum";
import type { DoctorListItem } from "@/lib/api/types";
import { fetchDoctors } from "@/lib/api/doctors";
import { fetchFacilities } from "@/lib/api/facilities";
import { EMPTY_HOME_HIGHLIGHTS, fetchHomeHighlights } from "@/lib/api/home";
import { fetchPharmacies } from "@/lib/api/pharmacies";
import { fetchProducts } from "@/lib/api/products";
import { fetchPublicSettings } from "@/lib/api/settings";
import { pageMetadata } from "@/lib/metadata";
import { t } from "@/i18n/t";

export const metadata: Metadata = pageMetadata(
  t("home.heroHeading"),
  t("home.heroLead"),
  { path: "/" },
);

const TOP_RATED_MIN_REVIEWS = 2;
const FEATURED_LIMIT = 6;
const QUICK_LINKS = 3;
const COMMUNITY_TOPICS = 3;

/** A list endpoint's total, or undefined when the module is off or it fails. */
async function totalOf(
  enabled: boolean,
  load: () => Promise<{ meta: { total: number } }>,
): Promise<number | undefined> {
  if (!enabled) {
    return undefined;
  }
  try {
    return (await load()).meta.total;
  } catch {
    return undefined;
  }
}

async function settle<T>(enabled: boolean, load: () => Promise<T>, empty: T) {
  if (!enabled) {
    return empty;
  }
  try {
    return await load();
  } catch {
    return empty;
  }
}

export default async function Home() {
  const settings = await fetchPublicSettings();
  const forumOn = settings.public_forum;
  const guidanceOn = settings.public_guidance;
  const pharmaciesOn = settings.public_pharmacies;
  const productsOn = settings.public_products;

  const [
    topRated,
    featured,
    highlights,
    topics,
    forumCategories,
    doctorsTotal,
    facilitiesTotal,
    pharmaciesTotal,
    productsTotal,
  ] = await Promise.all([
    settle(
      true,
      async () =>
        (
          await fetchDoctors({
            sort: "rating",
            min_reviews: TOP_RATED_MIN_REVIEWS,
            per_page: FEATURED_LIMIT,
          })
        ).data,
      [] as DoctorListItem[],
    ),
    settle(
      true,
      async () =>
        (await fetchDoctors({ featured: true, per_page: FEATURED_LIMIT })).data,
      [] as DoctorListItem[],
    ),
    // Popular specialties (also the hero's quick links), cities and the
    // latest reviews, in one cached call.
    settle(true, fetchHomeHighlights, EMPTY_HOME_HIGHLIGHTS),
    settle(
      forumOn,
      async () => (await fetchForumRecentTopics(COMMUNITY_TOPICS)).data,
      [] as ForumTopicSearchItem[],
    ),
    settle(forumOn, () => fetchForumCategories(), [] as ForumCategory[]),
    totalOf(true, () => fetchDoctors({ per_page: 1 })),
    totalOf(true, () => fetchFacilities({ per_page: 1 })),
    totalOf(pharmaciesOn, () => fetchPharmacies({ per_page: 1 })),
    totalOf(productsOn, () => fetchProducts({ per_page: 1 })),
  ]);

  const doctors = mergeHomeDoctors(topRated, featured, FEATURED_LIMIT);
  const forumTotal = forumCategories.length
    ? forumCategories.reduce((sum, c) => sum + (c.topics_count ?? 0), 0)
    : undefined;

  const totals = {
    doctors: doctorsTotal,
    facilities: facilitiesTotal,
    pharmacies: pharmaciesTotal,
    products: productsTotal,
    forumTopics: forumTotal,
  };
  const tiles = buildHomeTiles(
    {
      pharmacies: pharmaciesOn,
      products: productsOn,
      guidance: guidanceOn,
      forum: forumOn,
    },
    totals,
  );
  const heroStats = buildHeroStats(totals);

  const hasCommunity = forumOn && topics.length > 0;

  return (
    <div className="mx-auto w-full max-w-[1240px] lg:px-6">
      <HomeHero
        quickLinks={highlights.specialties.slice(0, QUICK_LINKS).map((s) => ({
          href: `/doctors?specialty=${encodeURIComponent(s.slug)}`,
          label: s.name,
        }))}
        stats={heroStats}
      />

      <div data-reveal="">
        <HomeDirectoryTiles
          tiles={tiles}
          className="px-5 pt-8 lg:px-0 lg:pt-20"
        />
      </div>

      {/* Personal, so right after the tiles; on-device only and absent
          until hydration (and when empty), hence no data-reveal. */}
      <HomeRecentlyViewed className="px-5 pt-10 lg:px-0 lg:pt-20" />

      <div data-reveal="">
        <HomeSpecialties
          specialties={highlights.specialties}
          className="px-5 pt-10 lg:px-0 lg:pt-20"
        />
      </div>

      <div data-reveal="">
        <HomeFeaturedDoctorsRail doctors={doctors} className="pt-10 lg:pt-20" />
      </div>

      <div data-reveal="">
        <HomeRecentReviews
          reviews={highlights.recent_reviews}
          className="px-5 pt-10 lg:px-0 lg:pt-20"
        />
      </div>

      {/* Mobile: guidance, then community. Desktop: community (7) beside
          guidance (5). */}
      <div
        data-reveal=""
        className="mt-10 grid gap-10 px-5 lg:mt-20 lg:grid-cols-12 lg:items-start lg:gap-x-6 lg:gap-y-0 lg:px-0"
      >
        {hasCommunity ? (
          <HomeCommunity
            topics={topics}
            className="order-2 lg:order-1 lg:col-span-7"
          />
        ) : null}
        <div
          className={
            hasCommunity
              ? "order-1 flex flex-col gap-10 lg:order-2 lg:col-span-5 lg:pt-[52px]"
              : "order-1 grid gap-10 lg:col-span-12 lg:grid-cols-2 lg:gap-6"
          }
        >
          {guidanceOn ? <HomeGuidanceCard /> : null}
        </div>
      </div>

      <div data-reveal="">
        <HomeCities
          cities={highlights.cities}
          className="mt-10 px-5 lg:mt-20 lg:px-0"
        />
      </div>

      <div data-reveal="">
        <HomeHowItWorksSection className="mt-14 px-5 lg:mt-20 lg:px-0" />
      </div>

      <div data-reveal="">
        <HomeForumTransparency
          showForum={forumOn}
          className="mt-10 px-5 lg:mt-14 lg:px-0"
        />
      </div>

      {/* Last before the footer, as in the D2a mockup. */}
      <HomeTrustRow
        items={[
          t("home.trustRowModerated"),
          t("home.trustRowClarity"),
          t("home.trustRowLocal"),
        ]}
        className="mt-10 px-5 lg:mt-14 lg:px-0"
      />
    </div>
  );
}

function mergeHomeDoctors(
  primary: DoctorListItem[],
  fallback: DoctorListItem[],
  limit: number,
) {
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
