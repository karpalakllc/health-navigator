import { ChipLink } from "@/components/ui/chip";
import { t, tFormat } from "@/i18n/t";

/**
 * The forum home's „Неодамнешни / Без одговор (N)“ switch: two chip links,
 * the current one marked. The count is left out when unknown.
 */
export function ForumViewChips({
  current,
  unansweredCount,
}: {
  current: "recent" | "unanswered";
  unansweredCount?: number;
}) {
  return (
    <nav aria-label={t("help.viewLabel")} className="flex flex-wrap gap-2">
      <ChipLink href="/forum" current={current === "recent"}>
        {t("help.viewLatest")}
      </ChipLink>
      <ChipLink
        href="/forum?view=unanswered"
        current={current === "unanswered"}
      >
        {unansweredCount === undefined
          ? t("help.viewUnanswered")
          : tFormat("help.viewUnansweredCount", { count: unansweredCount })}
      </ChipLink>
    </nav>
  );
}
