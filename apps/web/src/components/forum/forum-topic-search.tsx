import { filterInputClassName } from "@/components/directory/filter-form";
import { t } from "@/i18n/t";

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
      className="flex flex-col gap-2 sm:flex-row sm:items-center"
    >
      <input
        name="q"
        type="search"
        defaultValue={defaultQuery ?? ""}
        placeholder={t("forum.searchTopics")}
        className={`${filterInputClassName} min-h-[44px] flex-1`}
        aria-label={t("forum.searchTopics")}
      />
      <button
        type="submit"
        className="inline-flex min-h-[44px] shrink-0 items-center justify-center rounded-xl bg-primary px-5 text-sm font-semibold text-primary-foreground"
      >
        {t("common.search")}
      </button>
    </form>
  );
}
