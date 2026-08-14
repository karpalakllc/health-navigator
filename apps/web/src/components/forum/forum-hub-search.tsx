import Link from "next/link";
import { filterInputClassName } from "@/components/directory/filter-form";
import type { ForumCategory } from "@/lib/api/forum";
import { t } from "@/i18n/t";

type ForumHubSearchProps = {
  defaultQuery?: string;
  defaultCategory?: string;
  categories: ForumCategory[];
};

export function ForumHubSearch({
  defaultQuery = "",
  defaultCategory = "",
  categories,
}: ForumHubSearchProps) {
  return (
    <form
      action="/forum"
      method="get"
      className="filters-card filters-card-nested p-4 sm:p-5"
    >
      <div className="grid gap-3 sm:grid-cols-[1fr_minmax(140px,200px)_auto] sm:items-end">
        <label className="grid gap-1.5 text-sm">
          <span className="font-semibold text-foreground">
            {t("forum.searchTopics")}
          </span>
          <input
            name="q"
            type="search"
            defaultValue={defaultQuery}
            minLength={2}
            placeholder={t("forum.searchPlaceholder")}
            className={filterInputClassName}
            aria-label={t("forum.searchTopics")}
          />
        </label>
        <label className="grid gap-1.5 text-sm">
          <span className="font-semibold text-foreground">
            {t("forum.categories")}
          </span>
          <select
            name="category"
            defaultValue={defaultCategory}
            className={filterInputClassName}
          >
            <option value="">{t("forum.allCategories")}</option>
            {categories.map((category) => (
              <option key={category.slug} value={category.slug}>
                {category.name}
              </option>
            ))}
          </select>
        </label>
        <button
          type="submit"
          className="inline-flex min-h-[44px] items-center justify-center rounded-xl bg-primary px-5 text-sm font-extrabold text-primary-foreground hover:bg-primary/90"
        >
          {t("common.search")}
        </button>
      </div>
      {defaultQuery.length >= 2 ? (
        <p className="mt-3 text-sm text-muted-foreground">
          <Link
            href="/forum"
            className="font-semibold text-primary hover:underline"
          >
            {t("common.clearFilters")}
          </Link>
        </p>
      ) : null}
    </form>
  );
}
