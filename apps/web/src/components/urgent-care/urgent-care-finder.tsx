"use client";

import Link from "next/link";
import { useMemo, useState, useSyncExternalStore } from "react";
import { openStatusText } from "@/components/directory/open-status";
import { Button } from "@/components/ui/button";
import { Icon } from "@/components/ui/icons";
import { cn } from "@/lib/cn";
import { hasMapCoordinates, openStreetMapEmbedUrl } from "@/lib/maps";
import { telHref } from "@/lib/phone";
import {
  SERVICE_LABEL_KEYS,
  directionsUrl,
  formatDistance,
  sortByDistance,
  urgentOpenStatus,
  type Coordinates,
  type UrgentCarePlace,
} from "@/lib/urgent-care";
import { t, tFormat } from "@/i18n/t";

const subscribe = () => () => {};

type Locate =
  | { state: "idle" }
  | { state: "locating" }
  | { state: "denied" }
  | { state: "ready"; at: Coordinates };

/**
 * The list and map of „Каде веднаш“. Server-rendered in the API's order
 * (emergency departments first); after hydration it adds the open-now line
 * (only for confirmed hours) and, on request, sorts by distance.
 *
 * The position comes from the browser's geolocation, is used in this
 * component's memory only and is never sent anywhere — the only thing that
 * reaches the server is the city the visitor picks above.
 *
 * The map is one OpenStreetMap embed (no referrer) of the selected place;
 * places without coordinates get „Насоки“ to their address instead.
 */
export function UrgentCareFinder({ places }: { places: UrgentCarePlace[] }) {
  const mounted = useSyncExternalStore(
    subscribe,
    () => true,
    () => false,
  );
  const [now] = useState(() => new Date());
  const [locate, setLocate] = useState<Locate>({ state: "idle" });
  const [selected, setSelected] = useState<string | null>(
    () =>
      places.find((p) => hasMapCoordinates(p.latitude, p.longitude))?.slug ??
      null,
  );

  const anyCoordinates = places.some((p) =>
    hasMapCoordinates(p.latitude, p.longitude),
  );

  const rows = useMemo(
    () =>
      locate.state === "ready"
        ? sortByDistance(places, locate.at)
        : places.map((place) => ({ place, km: null as number | null })),
    [places, locate],
  );

  function findMe() {
    if (typeof navigator === "undefined" || !navigator.geolocation) {
      setLocate({ state: "denied" });
      return;
    }
    setLocate({ state: "locating" });
    navigator.geolocation.getCurrentPosition(
      (position) => {
        const at = {
          latitude: position.coords.latitude,
          longitude: position.coords.longitude,
        };
        setLocate({ state: "ready", at });
        const nearest = sortByDistance(places, at)[0];
        if (nearest?.km != null) setSelected(nearest.place.slug);
      },
      () => setLocate({ state: "denied" }),
      { enableHighAccuracy: false, maximumAge: 300_000, timeout: 15_000 },
    );
  }

  const selectedPlace = places.find((p) => p.slug === selected) ?? null;

  return (
    <div
      className={cn(
        "grid gap-6 lg:items-start lg:gap-8",
        anyCoordinates && "lg:grid-cols-[minmax(0,7fr)_minmax(0,5fr)]",
      )}
    >
      <div className="flex min-w-0 flex-col gap-4">
        {anyCoordinates ? (
          <div className="flex flex-col gap-1">
            <div>
              <Button
                variant="secondary"
                size="md"
                leadingIcon="locate"
                onClick={findMe}
                loading={locate.state === "locating"}
              >
                {locate.state === "locating"
                  ? t("urgentCare.locating")
                  : t("urgentCare.nearMe")}
              </Button>
            </div>
            <p className="type-meta text-ink-2" aria-live="polite">
              {locate.state === "denied"
                ? t("urgentCare.locationDenied")
                : locate.state === "ready"
                  ? t("urgentCare.sortedByDistance")
                  : t("urgentCare.nearMeHint")}
            </p>
          </div>
        ) : null}

        <ul className="flex flex-col gap-3">
          {rows.map(({ place, km }) => (
            <li key={place.slug}>
              <PlaceCard
                place={place}
                km={km}
                now={mounted ? now : null}
                selected={place.slug === selected}
                onShowOnMap={
                  hasMapCoordinates(place.latitude, place.longitude)
                    ? () => setSelected(place.slug)
                    : undefined
                }
              />
            </li>
          ))}
        </ul>
      </div>

      {/* No place has coordinates yet: no empty map box; each card has „Насоки“. */}
      {anyCoordinates ? (
        <aside
          aria-label={t("directory.map")}
          className="lg:sticky lg:top-[calc(var(--header-h)+1.5rem)]"
        >
          {selectedPlace &&
          hasMapCoordinates(selectedPlace.latitude, selectedPlace.longitude) ? (
            <div className="flex flex-col gap-2">
              <div className="overflow-hidden rounded-card bg-sand">
                <iframe
                  key={selectedPlace.slug}
                  title={tFormat("urgentCare.mapTitle", {
                    name: selectedPlace.name,
                  })}
                  src={openStreetMapEmbedUrl(
                    selectedPlace.latitude as number,
                    selectedPlace.longitude as number,
                  )}
                  className="aspect-[4/3] w-full border-0"
                  loading="lazy"
                  referrerPolicy="no-referrer"
                />
              </div>
              <p className="type-meta text-ink-2">{selectedPlace.name}</p>
            </div>
          ) : (
            <div className="flex min-h-40 items-center gap-3 rounded-card bg-sand p-5 type-meta text-ink-2">
              <Icon name="map-pin" size={24} className="shrink-0" />
              <span>{t("urgentCare.mapHint")}</span>
            </div>
          )}
        </aside>
      ) : null}
    </div>
  );
}

function PlaceCard({
  place,
  km,
  now,
  selected,
  onShowOnMap,
}: {
  place: UrgentCarePlace;
  km: number | null;
  /** null until hydrated: the open line depends on the minute. */
  now: Date | null;
  selected: boolean;
  onShowOnMap?: () => void;
}) {
  const status = now ? urgentOpenStatus(place, now) : null;
  const urgentTel = telHref(place.emergency_phone);
  const tel = telHref(place.phone);
  const where = [place.address, place.city].filter(Boolean).join(", ");

  return (
    <article
      aria-labelledby={`urgent-${place.slug}`}
      className={cn(
        "card flex flex-col gap-3 p-5",
        selected && "ring-2 ring-ink",
      )}
    >
      <div className="flex flex-col gap-1">
        <ul className="flex flex-wrap gap-1.5">
          {place.services.map((service) => (
            <li
              key={service}
              className="rounded-full bg-chip-tint px-2.5 py-0.5 text-sm font-semibold leading-5 text-ink"
            >
              {t(SERVICE_LABEL_KEYS[service])}
            </li>
          ))}
        </ul>
        <h3 id={`urgent-${place.slug}`} className="type-h3 text-ink">
          {place.name}
        </h3>
        {where ? <p className="type-body text-ink-2">{where}</p> : null}
        {km !== null ? (
          <p className="type-meta text-ink-2">
            {tFormat("urgentCare.distanceAway", {
              distance: formatDistance(km),
            })}
          </p>
        ) : null}
      </div>

      {place.hours_confirmed ? (
        status ? (
          <p
            className={cn(
              "flex items-center gap-2 type-meta",
              status.state === "closed"
                ? "text-ink-2"
                : "font-semibold text-care",
            )}
          >
            <Icon name="clock" size={20} />
            <span>{openStatusText(status)}</span>
          </p>
        ) : null
      ) : (
        <p className="flex items-start gap-2 type-meta text-ink-2">
          <Icon name="clock" size={20} className="mt-0.5 shrink-0" />
          <span>{t("urgentCare.hoursUnknown")}</span>
        </p>
      )}

      {place.note ? <p className="type-body text-ink">{place.note}</p> : null}

      <div className="flex flex-wrap gap-2">
        {urgentTel ? (
          <Button
            href={urgentTel}
            variant="primary"
            size="md"
            leadingIcon="phone"
            aria-label={tFormat("urgentCare.callUrgentName", {
              name: place.name,
            })}
          >
            {t("urgentCare.callUrgent")}
          </Button>
        ) : null}
        {tel ? (
          <Button
            href={tel}
            variant={urgentTel ? "secondary" : "primary"}
            size="md"
            leadingIcon="phone"
            aria-label={tFormat("urgentCare.callName", { name: place.name })}
          >
            {t("urgentCare.call")}
          </Button>
        ) : null}
        <Button
          href={directionsUrl(place)}
          variant="secondary"
          size="md"
          leadingIcon="navigation"
          target="_blank"
          rel="noopener noreferrer"
          aria-label={tFormat("urgentCare.directionsTo", { name: place.name })}
        >
          {t("urgentCare.directions")}
        </Button>
        {onShowOnMap ? (
          <Button
            variant="ghost"
            size="md"
            leadingIcon="map-pin"
            onClick={onShowOnMap}
            aria-pressed={selected}
          >
            {t("urgentCare.showOnMap")}
          </Button>
        ) : null}
        <Link
          href={`/facilities/${place.slug}`}
          className="inline-flex min-h-12 items-center px-2 type-body text-ink underline decoration-1 underline-offset-4"
          aria-label={tFormat("urgentCare.profileOf", { name: place.name })}
        >
          {t("urgentCare.profile")}
        </Link>
      </div>
    </article>
  );
}
