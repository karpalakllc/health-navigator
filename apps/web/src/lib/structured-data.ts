import type {
  FacilityType,
  PharmacyDetail,
  ReviewSummary,
} from "@/lib/api/types";

/**
 * schema.org payloads for facility and pharmacy pages, rendered through
 * components/seo/json-ld.tsx.
 *
 * Same rule as the doctor page: only facts the database holds. An empty field is
 * left out rather than sent as null, and a rating is only claimed when there are
 * published reviews behind it.
 */

type PlaceFacts = Pick<
  PharmacyDetail,
  | "name"
  | "description"
  | "city"
  | "address"
  | "latitude"
  | "longitude"
  | "phone"
  | "email"
  | "website"
  | "review_summary"
>;

const SCHEMA_TYPE_BY_FACILITY: Record<FacilityType, string> = {
  hospital: "Hospital",
  clinic: "MedicalClinic",
  laboratory: "DiagnosticLab",
};

export function facilitySchemaType(type: FacilityType): string {
  return SCHEMA_TYPE_BY_FACILITY[type] ?? "MedicalOrganization";
}

function aggregateRating(summary: ReviewSummary | undefined) {
  if (!summary || summary.count < 1 || summary.average_rating === null) {
    return {};
  }

  return {
    aggregateRating: {
      "@type": "AggregateRating",
      ratingValue: summary.average_rating,
      reviewCount: summary.count,
      bestRating: 5,
      worstRating: 1,
    },
  };
}

export function placeJsonLd(
  schemaType: string,
  place: PlaceFacts,
  url: string,
): Record<string, unknown> {
  const hasAddress = Boolean(place.address || place.city);
  const hasGeo = place.latitude !== null && place.longitude !== null;

  return {
    "@context": "https://schema.org",
    "@type": schemaType,
    name: place.name,
    url,
    ...(place.description ? { description: place.description } : {}),
    ...(hasAddress
      ? {
          address: {
            "@type": "PostalAddress",
            ...(place.address ? { streetAddress: place.address } : {}),
            ...(place.city ? { addressLocality: place.city } : {}),
            addressCountry: "MK",
          },
        }
      : {}),
    ...(hasGeo
      ? {
          geo: {
            "@type": "GeoCoordinates",
            latitude: place.latitude,
            longitude: place.longitude,
          },
        }
      : {}),
    ...(place.phone ? { telephone: place.phone } : {}),
    ...(place.email ? { email: place.email } : {}),
    ...(place.website ? { sameAs: place.website } : {}),
    ...aggregateRating(place.review_summary),
  };
}
