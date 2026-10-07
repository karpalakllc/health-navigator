import { ChipLink } from "@/components/ui/chip";
import { t } from "@/i18n/t";

export type ForumCategorySort = "latest" | "active" | "unanswered";

type ForumCategoryToolbarProps = {
  categorySlug: string;
  currentSort: ForumCategorySort;
  searchQuery?: string;
};

/**
 * „Најнови / Најактивни / Без одговор“ as chip links; the current one is
 * marked. „Без одговор“ lists the category's questions nobody has answered.
 */
export function ForumCategoryToolbar({
  categorySlug,
  currentSort,
  searchQuery,
}: ForumCategoryToolbarProps) {
  const base = `/forum/${categorySlug}`;
  const q = searchQuery?.trim();

  function href(sort: ForumCategorySort) {
    const params = new URLSearchParams();
    if (q) params.set("q", q);
    if (sort !== "latest") params.set("sort", sort);
    const query = params.toString();
    return query ? `${base}?${query}` : base;
  }

  return (
    <nav aria-label={t("forum.sortLabel")} className="flex flex-wrap gap-2">
      <ChipLink href={href("latest")} current={currentSort === "latest"}>
        {t("forum.sortLatest")}
      </ChipLink>
      <ChipLink href={href("active")} current={currentSort === "active"}>
        {t("forum.sortActive")}
      </ChipLink>
      <ChipLink
        href={href("unanswered")}
        current={currentSort === "unanswered"}
      >
        {t("help.viewUnanswered")}
      </ChipLink>
    </nav>
  );
}

/** Where „Нова тема“ goes from a category (sign-in first for guests). */
export function newTopicHref(categorySlug: string, isLoggedIn: boolean) {
  const target = `/forum/new?category=${encodeURIComponent(categorySlug)}`;

  return isLoggedIn ? target : `/login?redirect=${encodeURIComponent(target)}`;
}
