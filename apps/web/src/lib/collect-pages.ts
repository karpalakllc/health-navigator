export type Page<T> = {
  data: T[];
  meta: { last_page: number };
};

/**
 * Walks a paginated list endpoint and returns everything it yielded.
 *
 * Never throws: a page that fails ends this walk with what was collected so far,
 * so one bad listing costs only its own tail — never the caller's other
 * listings. `maxPages` bounds a runaway crawl.
 */
export async function collectPages<T>(
  fetchPage: (page: number) => Promise<Page<T>>,
  maxPages: number,
): Promise<T[]> {
  const items: T[] = [];

  for (let page = 1; page <= maxPages; page += 1) {
    let result: Page<T>;

    try {
      result = await fetchPage(page);
    } catch {
      break;
    }

    items.push(...result.data);

    if (page >= result.meta.last_page) {
      break;
    }
  }

  return items;
}
