import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/field";
import { t } from "@/i18n/t";

/** Search within one category (GET, keeps working without JavaScript). */
export function ForumTopicSearch({
  defaultQuery,
  action,
}: {
  defaultQuery?: string;
  action?: string;
}) {
  return (
    <form
      action={action}
      method="get"
      role="search"
      aria-label={t("forum.searchTopics")}
      className="flex flex-col gap-3 sm:flex-row sm:items-end"
    >
      <Input
        label={t("forum.searchTopics")}
        name="q"
        type="search"
        defaultValue={defaultQuery ?? ""}
        placeholder={t("forum.searchPlaceholder")}
        className="flex-1"
      />
      <Button type="submit" size="lg" leadingIcon="search">
        {t("common.search")}
      </Button>
    </form>
  );
}
