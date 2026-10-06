import { MapPlaceholder } from "@/components/directory/profile-parts";
import { Button } from "@/components/ui/button";
import {
  googleMapsDirectionsUrl,
  googleMapsSearchUrl,
  hasMapCoordinates,
  openStreetMapEmbedUrl,
} from "@/lib/maps";
import { t, tFormat } from "@/i18n/t";

/** OpenStreetMap embed when coordinates exist; the sand stand-in otherwise. */
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
    return mapFallbackUrl ? <MapPlaceholder href={mapFallbackUrl} /> : null;
  }

  const lat = latitude as number;
  const lng = longitude as number;

  return (
    <div className="flex flex-col gap-3">
      <div className="overflow-hidden rounded-xl bg-sand">
        <iframe
          title={tFormat("facilities.mapEmbedTitle", { name })}
          src={openStreetMapEmbedUrl(lat, lng)}
          className="aspect-[16/10] w-full border-0"
          loading="lazy"
          referrerPolicy="no-referrer-when-downgrade"
        />
      </div>
      <div className="flex flex-wrap gap-2">
        <Button
          href={googleMapsSearchUrl(lat, lng)}
          variant="secondary"
          size="sm"
          trailingIcon="external-link"
          target="_blank"
          rel="noopener noreferrer"
        >
          {t("facilities.openInGoogleMaps")}
        </Button>
        <Button
          href={googleMapsDirectionsUrl(lat, lng)}
          variant="soft"
          size="sm"
          leadingIcon="navigation"
          target="_blank"
          rel="noopener noreferrer"
        >
          {t("facilities.getDirections")}
        </Button>
      </div>
    </div>
  );
}
