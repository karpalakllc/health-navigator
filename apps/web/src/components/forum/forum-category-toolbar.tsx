import { ChipLink } from "@/components/ui/chip";
import { t } from "@/i18n/t";

type ForumCategoryToolbarProps = {
  categorySlug: string;
  currentSort: "latest" | "active";
  searchQuery?: string;
};

/** „Најнови / Најактивни“ as chip links; the current one is marked. */
export function ForumCategoryToolbar({
  categorySlug,
  currentSort,
  searchQuery,
}: ForumCategoryToolbarProps) {
  const base = `/forum/${categorySlug}`;
  const q = searchQuery?.trim();

  function href(sort: "latest" | "active") {
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
    </nav>
  );
}

/** Where „Нова тема“ goes from a category (sign-in first for guests). */
export function newTopicHref(categorySlug: string, isLoggedIn: boolean) {
  const target = `/forum/new?category=${encodeURIComponent(categorySlug)}`;

  return isLoggedIn ? target : `/login?redirect=${encodeURIComponent(target)}`;
}
