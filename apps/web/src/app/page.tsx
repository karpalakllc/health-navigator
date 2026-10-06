import type { Metadata } from "next";
import {
  HomeCommunity,
  HomePopularSpecialties,
  HomeTrustRow,
} from "@/components/home/home-community";
import {
  HomeDirectoryTiles,
  type HomeTile,
} from "@/components/home/home-directory-tiles";
import {
  HomeFeaturedDoctorsCard,
  HomeFeaturedDoctorsRail,
} from "@/components/home/home-featured-doctors";
import { HomeGuidanceCard } from "@/components/home/home-guidance-card";
import { HomeHero } from "@/components/home/home-hero";
import { HomeForumTransparency } from "@/components/layout/home-forum-transparency";
import { HomeHowItWorksSection } from "@/components/layout/home-how-it-works-section";
import type { ForumCategory, ForumTopicSearchItem } from "@/lib/api/forum";
import { fetchForumCategories, fetchForumRecentTopics } from "@/lib/api/forum";
import type { DoctorListItem, Specialty } from "@/lib/api/types";
import { fetchDoctors } from "@/lib/api/doctors";
import { fetchFacilities } from "@/lib/api/facilities";
import { fetchPharmacies } from "@/lib/api/pharmacies";
import { fetchProducts } from "@/lib/api/products";
import { fetchPublicSettings } from "@/lib/api/settings";
import { fetchSpecialties } from "@/lib/api/specialties";
import { pageMetadata } from "@/lib/metadata";
import { t, tCount, type MessageKey } from "@/i18n/t";

export const metadata: Metadata = pageMetadata(
  t("home.heroHeading"),
  t("home.heroLead"),
  { path: "/" },
);

const TOP_RATED_MIN_REVIEWS = 2;
const FEATURED_LIMIT = 6;
const QUICK_LINKS = 3;
const POPULAR_SPECIALTIES = 8;
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
    specialties,
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
    settle(true, fetchSpecialties, [] as Specialty[]),
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
  const bySize = [...specialties]
    .filter((s) => s.doctors_count > 0)
    .sort((a, b) => b.doctors_count - a.doctors_count);
  const forumTotal = forumCategories.length
    ? forumCategories.reduce((sum, c) => sum + (c.topics_count ?? 0), 0)
    : undefined;

  const tiles: HomeTile[] = [
    {
      href: "/doctors",
      label: t("nav.doctors"),
      icon: "stethoscope",
      sub: countLine("home.tileDoctorsCount", doctorsTotal),
      feature: true,
    },
    {
      href: "/facilities",
      label: t("nav.facilities"),
      icon: "building",
      sub: countLine("home.tileFacilitiesCount", facilitiesTotal),
    },
  ];
  if (pharmaciesOn) {
    tiles.push({
      href: "/pharmacies",
      label: t("nav.pharmacies"),
      icon: "pill",
      sub: countLine("home.tilePharmaciesCount", pharmaciesTotal),
    });
  }
  if (productsOn) {
    tiles.push({
      href: "/products",
      label: t("nav.products"),
      icon: "package",
      sub: countLine("home.tileProductsCount", productsTotal),
    });
  }
  if (guidanceOn) {
    tiles.push({
      href: "/guidance",
      label: t("nav.guidance"),
      icon: "compass",
      sub: t("home.tileGuidanceSub"),
      feature: true,
    });
  }
  if (forumOn) {
    tiles.push({
      href: "/forum",
      label: t("nav.forum"),
      icon: "message-circle",
      sub: countLine("home.tileForumCount", forumTotal),
    });
  }

  const hasCommunity = forumOn && topics.length > 0;
  const popular = bySize.slice(0, POPULAR_SPECIALTIES);

  return (
    <div className="mx-auto w-full max-w-[1240px] pb-14 lg:px-6 lg:pb-20">
      <HomeHero
        quickLinks={bySize.slice(0, QUICK_LINKS).map((s) => ({
          href: `/doctors?specialty=${encodeURIComponent(s.slug)}`,
          label: s.name,
        }))}
        aside={<HomeFeaturedDoctorsCard doctors={doctors} />}
      />

      <HomeDirectoryTiles
        tiles={tiles}
        className="px-5 pt-8 lg:px-0 lg:pt-20"
      />

      <HomeFeaturedDoctorsRail doctors={doctors} className="pt-10 lg:hidden" />

      {/* Mobile: guidance, community, trust stacked. Desktop: community (7)
          beside guidance + popular specialties (5), as in the D2a mockup. */}
      <div className="mt-10 grid gap-10 px-5 lg:mt-20 lg:grid-cols-12 lg:items-start lg:gap-x-6 lg:gap-y-0 lg:px-0">
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
          <HomePopularSpecialties
            specialties={popular}
            className="hidden lg:block"
          />
        </div>
      </div>

      <HomeTrustRow
        items={[
          t("home.trustRowModerated"),
          t("home.trustRowInformational"),
          t("home.trustRowLocal"),
        ]}
        className="mt-10 px-5 lg:mt-20 lg:px-0"
      />

      <HomeHowItWorksSection className="mt-14 px-5 lg:mt-20 lg:px-0" />

      <HomeForumTransparency
        showForum={forumOn}
        className="mt-10 px-5 lg:mt-14 lg:px-0"
      />
    </div>
  );
}

function countLine(key: MessageKey, total: number | undefined) {
  return total === undefined ? undefined : tCount(key, total);
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
