/**
 * Which anonymous directory listings may share Next's data cache.
 *
 * A cached listing is fetched without the visitor's address, so a cache miss
 * is metered against the web tier's single shared bucket, and every distinct
 * query string is its own cache entry. A listing is therefore only cacheable
 * when every parameter comes from a small, known set: a slug that exists in
 * the cached taxonomy, a sort from the enum, a boolean, an early page. Anything
 * else (free text, an unknown slug, page 9000) goes per-request and per-visitor,
 * so a client inventing URLs spends its own rate limit and leaves no cache
 * entries behind.
 */

/** Deeper pages are rarely visited; they are not worth a shared entry. */
export const MAX_CACHED_PAGE = 5;

export type DirectoryCacheRule = {
  /** Parameter → the values it may take (a taxonomy's slugs, an enum). */
  oneOf?: Record<string, readonly string[]>;
  booleans?: readonly string[];
  /** Parameter → largest accepted positive integer. */
  integers?: Record<string, number>;
};

const PAGING: Record<string, number> = { page: MAX_CACHED_PAGE, per_page: 50 };

function isBlank(value: unknown): boolean {
  return (
    value === undefined ||
    value === null ||
    (typeof value === "string" && value.trim() === "")
  );
}

function isBoundedInteger(value: unknown, max: number): boolean {
  const number =
    typeof value === "number"
      ? value
      : typeof value === "string" && /^\d+$/.test(value)
        ? Number(value)
        : NaN;

  return Number.isInteger(number) && number >= 1 && number <= max;
}

export function isCacheableDirectoryQuery(
  params: Record<string, unknown>,
  rule: DirectoryCacheRule = {},
): boolean {
  const integers = { ...PAGING, ...rule.integers };

  return Object.entries(params).every(([key, value]) => {
    if (isBlank(value)) {
      return true;
    }
    if (rule.booleans?.includes(key)) {
      return typeof value === "boolean" || value === "1" || value === "0";
    }
    if (key in integers) {
      return isBoundedInteger(value, integers[key]);
    }
    const allowed = rule.oneOf?.[key];

    return typeof value === "string" && allowed !== undefined
      ? allowed.includes(value)
      : false;
  });
}

/** The API's list endpoints reject pages past this (List*Request). */
export const MAX_LIST_PAGE = 1000;

/**
 * A ?page= from the URL as the API will accept it: a positive integer no
 * larger than MAX_LIST_PAGE, falling back to 1 for anything malformed.
 */
export function parseListPage(raw: string | undefined): number {
  if (raw === undefined || !/^\d+$/.test(raw)) {
    return 1;
  }

  return Math.min(Math.max(Number(raw), 1), MAX_LIST_PAGE);
}
