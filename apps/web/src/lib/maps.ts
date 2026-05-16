/** OpenStreetMap search link — no map SDK required. */
export function openStreetMapSearchUrl(query: string): string {
  return `https://www.openstreetmap.org/search?query=${encodeURIComponent(query)}`;
}

export function addressMapUrl(parts: {
  name?: string | null;
  address?: string | null;
  city?: string | null;
}): string | null {
  const query = [parts.name, parts.address, parts.city].filter(Boolean).join(", ");

  return query === "" ? null : openStreetMapSearchUrl(query);
}

export function hasMapCoordinates(
  latitude: number | null | undefined,
  longitude: number | null | undefined,
): boolean {
  return (
    latitude != null &&
    longitude != null &&
    Number.isFinite(latitude) &&
    Number.isFinite(longitude)
  );
}

/** Embedded map (OpenStreetMap) — no API key. */
export function openStreetMapEmbedUrl(latitude: number, longitude: number, delta = 0.012): string {
  const minLng = longitude - delta;
  const minLat = latitude - delta;
  const maxLng = longitude + delta;
  const maxLat = latitude + delta;

  return `https://www.openstreetmap.org/export/embed.html?bbox=${minLng}%2C${minLat}%2C${maxLng}%2C${maxLat}&layer=mapnik&marker=${latitude}%2C${longitude}`;
}

export function googleMapsSearchUrl(latitude: number, longitude: number): string {
  return `https://www.google.com/maps/search/?api=1&query=${latitude},${longitude}`;
}

export function googleMapsDirectionsUrl(latitude: number, longitude: number): string {
  return `https://www.google.com/maps/dir/?api=1&destination=${latitude},${longitude}`;
}
