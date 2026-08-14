import {
  googleMapsDirectionsUrl,
  googleMapsSearchUrl,
  hasMapCoordinates,
  openStreetMapEmbedUrl,
} from "@/lib/maps";
import { t, tFormat } from "@/i18n/t";

export function FacilityMapEmbed({
  latitude,
  longitude,
  name,
  mapFallbackUrl = null,
}: {
  latitude: number | null;
  longitude: number | null;
  name: string;
  mapFallbackUrl?: string | null;
}) {
  if (!hasMapCoordinates(latitude, longitude)) {
    if (!mapFallbackUrl) {
      return null;
    }

    return (
      <div className="rounded-2xl border border-dashed border-border bg-muted/20 p-5 text-center">
        <p className="text-sm text-muted-foreground">
          {t("facilities.mapCoordinatesMissing")}
        </p>
        <a
          href={mapFallbackUrl}
          target="_blank"
          rel="noopener noreferrer"
          className="mt-3 inline-flex items-center rounded-full bg-primary px-4 py-2 text-xs font-semibold text-primary-foreground transition hover:bg-primary/92"
        >
          {t("directory.viewOnMap")} →
        </a>
      </div>
    );
  }

  const lat = latitude as number;
  const lng = longitude as number;

  return (
    <div className="motion-safe:animate-fade-up space-y-3">
      <div className="overflow-hidden rounded-2xl border border-border shadow-[0_16px_44px_-28px_rgb(15_23_42/0.45)]">
        <iframe
          title={tFormat("facilities.mapEmbedTitle", { name })}
          src={openStreetMapEmbedUrl(lat, lng)}
          className="h-56 w-full border-0 sm:h-64"
          loading="lazy"
          referrerPolicy="no-referrer-when-downgrade"
        />
      </div>
      <div className="flex flex-wrap gap-2">
        <a
          href={googleMapsSearchUrl(lat, lng)}
          target="_blank"
          rel="noopener noreferrer"
          className="inline-flex items-center rounded-full border border-border bg-card px-3 py-1.5 text-xs font-semibold text-foreground transition hover:border-primary/30 hover:text-primary"
        >
          {t("facilities.openInGoogleMaps")} →
        </a>
        <a
          href={googleMapsDirectionsUrl(lat, lng)}
          target="_blank"
          rel="noopener noreferrer"
          className="inline-flex items-center rounded-full bg-primary px-3 py-1.5 text-xs font-semibold text-primary-foreground shadow-sm transition hover:bg-primary/92"
        >
          {t("facilities.getDirections")} →
        </a>
      </div>
    </div>
  );
}
