export const SEARCH_MIN_LENGTH = 2;

export function normalizeSearchQuery(
  value: string | undefined,
): string | undefined {
  if (!value) {
    return undefined;
  }

  const trimmed = value.trim();

  if (trimmed.length < SEARCH_MIN_LENGTH) {
    return undefined;
  }

  return trimmed;
}

export function searchQueryParams(
  q: string | undefined,
  city?: string | undefined,
): Record<string, string> {
  const params: Record<string, string> = {};
  const normalized = normalizeSearchQuery(q);

  if (normalized) {
    params.q = normalized;
  }

  const cityTrimmed = city?.trim();

  if (cityTrimmed) {
    params.city = cityTrimmed;
  }

  return params;
}

function toQueryString(params: Record<string, string>): string {
  const search = new URLSearchParams(params);
  const query = search.toString();

  return query ? `?${query}` : "";
}

export function directorySearchHref(
  basePath: string,
  q: string | undefined,
  city?: string | undefined,
  extra?: Record<string, string>,
): string {
  return `${basePath}${toQueryString({ ...searchQueryParams(q, city), ...extra })}`;
}
