import type { Metadata } from "next";
import { redirect } from "next/navigation";
import { UrgentCarePage } from "@/components/urgent-care/urgent-care-page";
import { fetchLocationCities } from "@/lib/api/locations";
import { fetchUrgentCare, fetchUrgentCareCities } from "@/lib/api/urgent-care";
import { pageMetadata } from "@/lib/metadata";
import { citySlug, isUrgentService, urgentCareHref } from "@/lib/urgent-care";
import { t } from "@/i18n/t";

type Props = {
  searchParams: Promise<{ city?: string | string[]; type?: string | string[] }>;
};

function single(value: string | string[] | undefined): string | undefined {
  return Array.isArray(value) ? value[0] : value;
}

export async function generateMetadata({
  searchParams,
}: Props): Promise<Metadata> {
  const params = await searchParams;
  const filtered = Boolean(single(params.city) || single(params.type));

  return pageMetadata(t("urgentCare.metaTitle"), t("urgentCare.description"), {
    path: "/urgent-care",
    // A filtered view is a variant of this page or of a city page.
    noIndex: filtered,
    follow: true,
  });
}

/**
 * „Каде веднаш“ for the whole country, and the deep-link entry point:
 * `/urgent-care?city=Битола&type=ed` redirects to the city's own page
 * (`/urgent-care/bitola?type=ed`) when the city is on the territorial list.
 * A city that is not stays here as a filtered, unindexed view.
 */
export default async function UrgentCareIndex({ searchParams }: Props) {
  const params = await searchParams;
  const city = single(params.city)?.trim() || null;
  const rawType = single(params.type);
  const type = isUrgentService(rawType) ? rawType : null;

  if (city && citySlug(city)) {
    redirect(urgentCareHref({ city, type }));
  }

  const [list, cities, knownCities] = await Promise.all([
    fetchUrgentCare(
      { city: city ?? undefined, type: type ?? undefined },
      !city,
    ),
    fetchUrgentCareCities().catch(() => []),
    fetchLocationCities().catch(() => []),
  ]);

  return (
    <UrgentCarePage
      cityName={city}
      type={type}
      list={list}
      cities={cities}
      knownCities={knownCities}
    />
  );
}
