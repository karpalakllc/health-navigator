import { Button } from "@/components/ui/button";
import { t } from "@/i18n/t";

type PaginationProps = {
  basePath: string;
  currentPage: number;
  lastPage: number;
  total: number;
  searchParams: Record<string, string | undefined>;
  /** The nav's name; give each one its own when a page has two. */
  label?: string;
};

function buildHref(
  basePath: string,
  page: number,
  searchParams: Record<string, string | undefined>,
  pageParam: string,
): string {
  const params = new URLSearchParams();

  for (const [key, value] of Object.entries(searchParams)) {
    if (value) {
      params.set(key, value);
    }
  }

  if (page > 1) {
    params.set(pageParam, String(page));
  }

  const query = params.toString();

  return query ? `${basePath}?${query}` : basePath;
}

/** „Претходна“ / „Следна“ pills with „Страница 1 од 3 (24 вкупно)“ between. */
export function Pagination({
  basePath,
  currentPage,
  lastPage,
  total,
  searchParams,
  pageParam = "page",
  label = t("pagination.label"),
}: PaginationProps & { pageParam?: string }) {
  if (lastPage <= 1) {
    return null;
  }

  return (
    <nav
      data-track="pagination"
      className="flex flex-col items-center gap-3 pt-2 sm:flex-row sm:justify-between"
      aria-label={label}
    >
      <p className="type-meta order-first text-ink-2 sm:order-none">
        {t("pagination.page")} {currentPage} {t("pagination.of")} {lastPage} (
        {total} {t("pagination.total")})
      </p>
      <div className="flex w-full gap-2 sm:w-auto sm:order-last">
        {currentPage > 1 ? (
          <Button
            href={buildHref(basePath, currentPage - 1, searchParams, pageParam)}
            variant="secondary"
            leadingIcon="chevron-left"
            className="flex-1 sm:flex-none"
          >
            {t("pagination.previous")}
          </Button>
        ) : null}
        {currentPage < lastPage ? (
          <Button
            href={buildHref(basePath, currentPage + 1, searchParams, pageParam)}
            variant="secondary"
            trailingIcon="chevron-right"
            className="flex-1 sm:flex-none"
          >
            {t("pagination.next")}
          </Button>
        ) : null}
      </div>
    </nav>
  );
}
