import { MK_PLACE_GROUPS, findPlaceByName, type Place } from "@/lib/mk-places";
import {
  officeHoursRows,
  openStatus,
  type OpenStatus,
} from "@/lib/office-hours";
import { googleMapsDirectionsUrl } from "@/lib/maps";

/*
 * „Каде веднаш“ (docs/urgent-care.md): the link contract, city pages and the
 * browser-only distance sort. Nothing here talks to the server.
 */

/** The `type` query parameter: what kind of urgent care. */
export const URGENT_SERVICES = ["ed", "ems", "clinic", "dental"] as const;
export type UrgentService = (typeof URGENT_SERVICES)[number];

export function isUrgentService(value: unknown): value is UrgentService {
  return (
    typeof value === "string" &&
    (URGENT_SERVICES as readonly string[]).includes(value)
  );
}

export type UrgentCarePlace = {
  slug: string;
  name: string;
  type: "clinic" | "hospital" | "laboratory";
  city: string | null;
  address: string | null;
  latitude: number | null;
  longitude: number | null;
  phone: string | null;
  emergency_phone: string | null;
  services: UrgentService[];
  /**
   * The emergency department: confirmed, or likely (a public general or
   * clinical hospital staff have not checked yet — shown as „непотврдено“).
   * Optional until every API serves it.
   */
  ed_status?: "confirmed" | "unconfirmed_likely" | null;
  is_open_24h: boolean;
  /** Same format as office_hours; null = not confirmed. */
  emergency_hours: Record<string, string> | null;
  hours_confirmed: boolean;
  note: string | null;
  checked_at: string | null;
};

export type UrgentCareCity = {
  name: string;
  total: number;
  ed: number;
  ems: number;
  clinic: number;
  dental: number;
};

/** The city's stable URL slug: the official Latin name („Битола“ → bitola). */
export function citySlug(name: string): string | null {
  return findPlaceByName(name.trim())?.place.id ?? null;
}

/** /urgent-care/bitola → the place (Скопје's ten municipalities included). */
export function placeBySlug(slug: string): Place | undefined {
  const key = slug.trim().toLowerCase();
  for (const group of MK_PLACE_GROUPS) {
    for (const place of [group.city, ...group.places]) {
      if (place.id === key) return place;
    }
  }
  return undefined;
}

/**
 * The deep link other pages (guidance outcomes, guides) use. A known city
 * goes to its indexable page, anything else to the query form; `type`
 * narrows the list.
 *
 *   urgentCareHref({ city: "Битола", type: "ed" }) → /urgent-care/bitola?type=ed
 *   urgentCareHref({ city: "Струмица" })           → /urgent-care/strumica
 *   urgentCareHref({})                              → /urgent-care
 */
export function urgentCareHref({
  city,
  type,
}: {
  city?: string | null;
  type?: UrgentService | null;
}): string {
  const query = type ? `?type=${type}` : "";
  const name = city?.trim();
  if (!name) return `/urgent-care${query}`;
  const slug = citySlug(name);
  if (slug) return `/urgent-care/${slug}${query}`;
  const params = new URLSearchParams({ city: name });
  if (type) params.set("type", type);
  return `/urgent-care?${params.toString()}`;
}

/**
 * Open status for the urgent service — only from hours the institution
 * confirmed. Unknown hours give null: the page then says so and never
 * claims „open now“.
 */
export function urgentOpenStatus(
  place: Pick<UrgentCarePlace, "is_open_24h" | "emergency_hours">,
  now: Date,
): OpenStatus | null {
  if (place.is_open_24h) return { state: "open24" };
  if (!place.emergency_hours) return null;
  return openStatus(officeHoursRows(place.emergency_hours, now), now);
}

export type Coordinates = { latitude: number; longitude: number };

/** Great-circle distance in kilometres. */
export function distanceKm(a: Coordinates, b: Coordinates): number {
  const rad = (deg: number) => (deg * Math.PI) / 180;
  const dLat = rad(b.latitude - a.latitude);
  const dLng = rad(b.longitude - a.longitude);
  const h =
    Math.sin(dLat / 2) ** 2 +
    Math.cos(rad(a.latitude)) *
      Math.cos(rad(b.latitude)) *
      Math.sin(dLng / 2) ** 2;
  return 6371 * 2 * Math.asin(Math.min(1, Math.sqrt(h)));
}

/**
 * Nearest first; places without coordinates keep their order at the end.
 * The visitor's position never leaves the browser.
 */
export function sortByDistance<
  T extends Pick<UrgentCarePlace, "latitude" | "longitude">,
>(places: T[], from: Coordinates): { place: T; km: number | null }[] {
  const withDistance = places.map((place, index) => ({
    place,
    index,
    km:
      place.latitude != null && place.longitude != null
        ? distanceKm(from, {
            latitude: place.latitude,
            longitude: place.longitude,
          })
        : null,
  }));
  withDistance.sort((a, b) => {
    if (a.km === null && b.km === null) return a.index - b.index;
    if (a.km === null) return 1;
    if (b.km === null) return -1;
    return a.km - b.km;
  });
  return withDistance.map(({ place, km }) => ({ place, km }));
}

/** „1,2 km“ / „850 m“. */
export function formatDistance(km: number): string {
  if (km < 1) return `${Math.max(50, Math.round((km * 1000) / 50) * 50)} m`;
  return `${(Math.round(km * 10) / 10).toLocaleString("mk-MK")} km`;
}

/**
 * Directions in the visitor's maps app: to the coordinates when we have
 * them, else to the name and address (Google Maps URLs, no API key).
 */
export function directionsUrl(
  place: Pick<
    UrgentCarePlace,
    "name" | "address" | "city" | "latitude" | "longitude"
  >,
): string {
  if (place.latitude != null && place.longitude != null) {
    return googleMapsDirectionsUrl(place.latitude, place.longitude);
  }
  const destination = [place.name, place.address, place.city]
    .filter(Boolean)
    .join(", ");
  return `https://www.google.com/maps/dir/?api=1&destination=${encodeURIComponent(destination)}`;
}

/** Message keys of the service names, in the API's order. */
export const SERVICE_LABEL_KEYS = {
  ed: "urgentCare.serviceEd",
  ems: "urgentCare.serviceEms",
  clinic: "urgentCare.serviceClinic",
  dental: "urgentCare.serviceDental",
} as const satisfies Record<UrgentService, string>;
