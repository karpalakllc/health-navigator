import Link from "next/link";
import { t } from "@/i18n/t";

type PaginationProps = {
  basePath: string;
  currentPage: number;
  lastPage: number;
  total: number;
  searchParams: Record<string, string | undefined>;
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

export function Pagination({
  basePath,
  currentPage,
  lastPage,
  total,
  searchParams,
  pageParam = "page",
}: PaginationProps & { pageParam?: string }) {
  if (lastPage <= 1) {
    return null;
  }

  return (
    <nav
      className="flex flex-wrap items-center justify-between gap-2 text-sm text-muted-foreground"
      aria-label="Pagination"
    >
      <p>
        {t("pagination.page")} {currentPage} {t("pagination.of")} {lastPage} (
        {total} {t("pagination.total")})
      </p>
      <div className="flex gap-3">
        {currentPage > 1 ? (
          <Link
            href={buildHref(basePath, currentPage - 1, searchParams, pageParam)}
            className="font-medium text-primary underline-offset-4 hover:text-primary/80 hover:underline"
          >
            {t("pagination.previous")}
          </Link>
        ) : null}
        {currentPage < lastPage ? (
          <Link
            href={buildHref(basePath, currentPage + 1, searchParams, pageParam)}
            className="font-medium text-primary underline-offset-4 hover:text-primary/80 hover:underline"
          >
            {t("pagination.next")}
          </Link>
        ) : null}
      </div>
    </nav>
  );
}
