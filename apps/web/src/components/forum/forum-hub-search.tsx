import { Button, TextLink } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import { Input, Select } from "@/components/ui/field";
import type { ForumCategory } from "@/lib/api/forum";
import { t } from "@/i18n/t";

type ForumHubSearchProps = {
  defaultQuery?: string;
  defaultCategory?: string;
  categories: ForumCategory[];
};

/** GET /forum?q=…&category=… — works without JavaScript. */
export function ForumHubSearch({
  defaultQuery = "",
  defaultCategory = "",
  categories,
}: ForumHubSearchProps) {
  return (
    <Card padding="md">
      <form
        action="/forum"
        method="get"
        // A one-letter query is ignored by the page (≥2); no English bubble.
        noValidate
        role="search"
        aria-label={t("forum.searchTopics")}
        className="grid gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(12rem,15rem)_auto] lg:items-end"
      >
        <Input
          label={t("forum.searchTopics")}
          name="q"
          type="search"
          defaultValue={defaultQuery}
          minLength={2}
          placeholder={t("forum.searchPlaceholder")}
        />
        <Select
          label={t("forum.categories")}
          name="category"
          defaultValue={defaultCategory}
        >
          <option value="">{t("forum.allCategories")}</option>
          {categories.map((category) => (
            <option key={category.slug} value={category.slug}>
              {category.name}
            </option>
          ))}
        </Select>
        <Button
          type="submit"
          size="lg"
          leadingIcon="search"
          className="w-full lg:min-h-14 lg:w-auto"
        >
          {t("common.search")}
        </Button>
      </form>
      {defaultQuery.length >= 2 ? (
        <p className="mt-2">
          <TextLink href="/forum">{t("common.clearFilters")}</TextLink>
        </p>
      ) : null}
    </Card>
  );
}
