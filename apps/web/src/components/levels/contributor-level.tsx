import { Tag } from "@/components/ui/tag";
import type { LevelLadder } from "@/lib/api/levels";
import { cn } from "@/lib/cn";
import { levelTitle } from "@/lib/levels";
import { t } from "@/i18n/t";

/**
 * W8-C: the contributor title next to a username („Активен рецензент“,
 * „Помошник“). A quiet peach chip; nothing at all without a level. The API
 * already leaves the level out for staff, suspended and deleted accounts.
 */
export function ContributorLevel({
  ladder,
  level,
  className,
}: {
  ladder: LevelLadder;
  level: number | null | undefined;
  className?: string;
}) {
  const title = levelTitle(ladder, level);

  if (title === null) {
    return null;
  }

  return (
    <Tag
      tone="tint"
      icon="award"
      data-level={level}
      className={cn("min-h-7 px-2.5 text-[0.875rem]", className)}
    >
      <span className="sr-only">{t("levels.chipPrefix")} </span>
      {title}
    </Tag>
  );
}
