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
