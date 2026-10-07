import type { Metadata } from "next";
import { notFound } from "next/navigation";
import { UrgentCarePage } from "@/components/urgent-care/urgent-care-page";
import { fetchLocationCities } from "@/lib/api/locations";
import { fetchUrgentCare, fetchUrgentCareCities } from "@/lib/api/urgent-care";
import { pageMetadata } from "@/lib/metadata";
import { isUrgentService, placeBySlug } from "@/lib/urgent-care";
import { tFormat } from "@/i18n/t";

type Props = {
  params: Promise<{ city: string }>;
  searchParams: Promise<{ type?: string | string[] }>;
};

function typeFrom(value: string | string[] | undefined) {
  const raw = Array.isArray(value) ? value[0] : value;
  return isUrgentService(raw) ? raw : null;
}

export async function generateMetadata({
  params,
  searchParams,
}: Props): Promise<Metadata> {
  const { city } = await params;
  const place = placeBySlug(city);

  if (!place) {
    return {};
  }

  const type = typeFrom((await searchParams).type);
  const list = await fetchUrgentCare({ city: place.name }, true).catch(
    () => null,
  );

  return pageMetadata(
    tFormat("urgentCare.cityTitle", { city: place.name }),
    tFormat("urgentCare.cityDescription", { city: place.name }),
    {
      path: `/urgent-care/${place.id}`,
      // Indexed only with places to show, and without the service filter.
      noIndex: type !== null || !list || list.data.length === 0,
      follow: true,
    },
  );
}

/** „Итна помош во Битола“: the indexable city page of „Каде веднаш“. */
export default async function UrgentCareCityPage({
  params,
  searchParams,
}: Props) {
  const { city } = await params;
  const place = placeBySlug(city);

  if (!place) {
    notFound();
  }

  const type = typeFrom((await searchParams).type);
  const [list, cities, knownCities] = await Promise.all([
    fetchUrgentCare({ city: place.name, type: type ?? undefined }, true),
    fetchUrgentCareCities().catch(() => []),
    fetchLocationCities().catch(() => []),
  ]);

  return (
    <UrgentCarePage
      cityName={place.name}
      type={type}
      list={list}
      cities={cities}
      knownCities={knownCities}
    />
  );
}
