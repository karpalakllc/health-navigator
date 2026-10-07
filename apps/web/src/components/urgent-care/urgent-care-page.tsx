import Link from "next/link";
import { Breadcrumbs } from "@/components/directory/breadcrumbs";
import { HelpfulFeedback } from "@/components/feedback/helpful-feedback";
import { CityPicker } from "@/components/home/city-picker";
import { JsonLd } from "@/components/seo/json-ld";
import { Button } from "@/components/ui/button";
import { ChipLink } from "@/components/ui/chip";
import { Notice } from "@/components/ui/notice";
import { EmergencyStrip } from "@/components/urgent-care/emergency-strip";
import { UrgentCareFinder } from "@/components/urgent-care/urgent-care-finder";
import type { UrgentCareList } from "@/lib/api/urgent-care";
import type { LocationCity } from "@/lib/api/locations";
import { breadcrumbJsonLd } from "@/lib/structured-data";
import { absoluteUrl } from "@/lib/site-url";
import {
  SERVICE_LABEL_KEYS,
  URGENT_SERVICES,
  citySlug,
  urgentCareHref,
  type UrgentCareCity,
  type UrgentCarePlace,
  type UrgentService,
} from "@/lib/urgent-care";
import { t, tCount, tFormat } from "@/i18n/t";

/** ФЗОМ's monthly on-duty pharmacy schedule (checked 2026-10-07). */
const FZOM_ON_DUTY_PHARMACIES = "https://fzo.org.mk/dezurni-apteki";

/**
 * schema.org for the list: each place as Hospital (a hospital with an
 * emergency department — Hospital is an EmergencyService subtype) or
 * EmergencyService, with only the facts we hold.
 */
export function urgentCareJsonLd(
  places: UrgentCarePlace[],
  pageUrl: string,
  name: string,
): Record<string, unknown> {
  return {
    "@context": "https://schema.org",
    "@type": "ItemList",
    name,
    url: pageUrl,
    numberOfItems: places.length,
    itemListElement: places.map((place, index) => ({
      "@type": "ListItem",
      position: index + 1,
      item: {
        "@type":
          place.type === "hospital" && place.services.includes("ed")
            ? "Hospital"
            : "EmergencyService",
        name: place.name,
        url: absoluteUrl(`/facilities/${place.slug}`),
        ...(place.address || place.city
          ? {
              address: {
                "@type": "PostalAddress",
                ...(place.address ? { streetAddress: place.address } : {}),
                ...(place.city ? { addressLocality: place.city } : {}),
                addressCountry: "MK",
              },
            }
          : {}),
        ...(place.latitude != null && place.longitude != null
          ? {
              geo: {
                "@type": "GeoCoordinates",
                latitude: place.latitude,
                longitude: place.longitude,
              },
            }
          : {}),
        ...(place.emergency_phone || place.phone
          ? { telephone: place.emergency_phone ?? place.phone }
          : {}),
        ...(place.is_open_24h
          ? {
              openingHoursSpecification: {
                "@type": "OpeningHoursSpecification",
                dayOfWeek: [
                  "Monday",
                  "Tuesday",
                  "Wednesday",
                  "Thursday",
                  "Friday",
                  "Saturday",
                  "Sunday",
                ],
                opens: "00:00",
                closes: "23:59",
              },
            }
          : {}),
      },
    })),
  };
}

/**
 * „Каде веднаш“: the 194/112 strip, the city and service filters, the list
 * with its map, and short notes (which service when, on-duty pharmacies).
 * Shared by /urgent-care and /urgent-care/[city].
 */
export function UrgentCarePage({
  cityName,
  type,
  list,
  cities,
  knownCities,
}: {
  /** The chosen city („Битола“), or null for the whole country. */
  cityName: string | null;
  type: UrgentService | null;
  list: UrgentCareList;
  cities: UrgentCareCity[];
  knownCities: LocationCity[];
}) {
  const title = cityName
    ? tFormat("urgentCare.cityTitle", { city: cityName })
    : t("urgentCare.title");
  const pageUrl = absoluteUrl(urgentCareHref({ city: cityName }));
  const count = list.data.length;
  const breadcrumbs = [
    { label: t("common.home"), href: "/" },
    { label: t("urgentCare.breadcrumb"), href: "/urgent-care" },
    ...(cityName ? [{ label: cityName }] : []),
  ];
  const feedbackItem = `urgent-care:${(cityName && citySlug(cityName)) || "all"}`;

  return (
    <div className="mx-auto flex w-full max-w-[1240px] flex-col gap-6 px-5 pb-14 pt-4 lg:gap-8 lg:px-6 lg:pb-20 lg:pt-8">
      <div>
        <Breadcrumbs items={breadcrumbs} className="mb-3" />
        <header className="flex flex-col gap-2">
          <h1 className="type-h1 text-ink">{title}</h1>
          <p className="measure type-reading text-ink-2">
            {t("urgentCare.lead")}
          </p>
        </header>
      </div>

      <EmergencyStrip />

      <section
        aria-label={t("directory.filters")}
        className="flex flex-col gap-4"
      >
        <form
          action="/urgent-care"
          method="get"
          className="flex flex-wrap items-center gap-2 rounded-pill bg-white p-1.5 shadow-card ring-1 ring-line sm:w-fit"
        >
          <span className="sr-only">{t("urgentCare.cityLabel")}</span>
          <CityPicker
            defaultCity={cityName ?? undefined}
            knownCities={knownCities}
            className="min-w-0 flex-1"
          />
          {type ? <input type="hidden" name="type" value={type} /> : null}
          <Button type="submit" variant="primary" size="md">
            {t("urgentCare.show")}
          </Button>
        </form>

        <nav aria-label={t("urgentCare.serviceLabel")}>
          <ul className="flex flex-wrap gap-2">
            <li>
              <ChipLink
                href={urgentCareHref({ city: cityName })}
                current={type === null}
              >
                {t("urgentCare.serviceAll")}
              </ChipLink>
            </li>
            {URGENT_SERVICES.map((service) => (
              <li key={service}>
                <ChipLink
                  href={urgentCareHref({ city: cityName, type: service })}
                  current={type === service}
                >
                  {t(SERVICE_LABEL_KEYS[service])}
                </ChipLink>
              </li>
            ))}
          </ul>
        </nav>
      </section>

      <section aria-labelledby="urgent-results" className="flex flex-col gap-4">
        <h2 id="urgent-results" className="type-h2 text-ink">
          {cityName
            ? tCount("urgentCare.resultsIn", count, { city: cityName })
            : tCount("urgentCare.results", count)}
        </h2>

        {count === 0 ? (
          <div className="flex flex-col items-start gap-3 rounded-card bg-sand p-5">
            <p className="type-label text-ink">
              {cityName
                ? tFormat("urgentCare.emptyTitle", { city: cityName })
                : t("urgentCare.emptyTitleAll")}
            </p>
            <p className="type-body text-ink">{t("urgentCare.emptyBody")}</p>
            {cityName ? (
              <Button href="/urgent-care" variant="secondary" size="md">
                {t("urgentCare.allCities")}
              </Button>
            ) : null}
          </div>
        ) : (
          <UrgentCareFinder places={list.data} />
        )}

        <p className="type-meta text-ink-2">{t("urgentCare.dataNote")}</p>
      </section>

      <div className="grid gap-4 lg:grid-cols-2 lg:gap-6">
        <section
          aria-labelledby="urgent-what"
          className="flex flex-col gap-3 rounded-card bg-white p-5 ring-1 ring-line lg:p-6"
        >
          <h2 id="urgent-what" className="type-h3 text-ink">
            {t("urgentCare.whatTitle")}
          </h2>
          <ul className="flex list-disc flex-col gap-2 pl-5 type-body text-ink marker:text-ink-2">
            <li>{t("urgentCare.whatEd")}</li>
            <li>{t("urgentCare.whatEms")}</li>
            <li>{t("urgentCare.whatClinic")}</li>
            <li>{t("urgentCare.whatDental")}</li>
          </ul>
          <p className="type-body text-ink">{t("urgentCare.noReferral")}</p>
          <p>
            <Link
              href="/guides"
              className="inline-flex min-h-11 items-center type-body text-ink underline decoration-1 underline-offset-4"
            >
              {t("urgentCare.guidesLink")}
            </Link>
          </p>
        </section>

        <section aria-labelledby="urgent-pharmacies">
          <Notice
            tone="info"
            icon="pill"
            title={
              <span id="urgent-pharmacies">
                {t("urgentCare.pharmaciesTitle")}
              </span>
            }
          >
            <p>{t("urgentCare.pharmaciesBody")}</p>
            <p className="mt-2">
              <a
                href={FZOM_ON_DUTY_PHARMACIES}
                target="_blank"
                rel="noopener noreferrer"
                className="link-underline"
              >
                {t("urgentCare.pharmaciesLink")}
              </a>
            </p>
          </Notice>
        </section>
      </div>

      {cities.length > 0 ? (
        <section
          aria-labelledby="urgent-cities"
          className="flex flex-col gap-3"
        >
          <h2 id="urgent-cities" className="type-h3 text-ink">
            {t("urgentCare.citiesTitle")}
          </h2>
          <ul className="flex flex-wrap gap-2">
            {cities.map((city) => (
              <li key={city.name}>
                <ChipLink
                  href={urgentCareHref({ city: city.name })}
                  current={cityName === city.name}
                >
                  {city.name}
                </ChipLink>
              </li>
            ))}
          </ul>
        </section>
      ) : null}

      <HelpfulFeedback item={feedbackItem} />

      {count > 0 ? (
        <JsonLd data={urgentCareJsonLd(list.data, pageUrl, title)} />
      ) : null}
      {(() => {
        const crumbs = breadcrumbJsonLd(
          breadcrumbs.map((item) => ({
            name: item.label,
            url: item.href ? absoluteUrl(item.href) : pageUrl,
          })),
        );
        return crumbs ? <JsonLd data={crumbs} /> : null;
      })()}
    </div>
  );
}
