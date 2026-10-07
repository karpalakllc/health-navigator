import type { LevelLadder, MyLevels } from "@/lib/api/levels";
import { t, tCount, tFormat, type MessageKey } from "@/i18n/t";

/** W8-C ladders (docs/levels.md): five titles for reviewers, four for the forum. */
export const LADDER_SIZE: Record<LevelLadder, number> = {
  review: 5,
  forum: 4,
};

/**
 * The Macedonian title of a level, or null for no level (0, missing, or a
 * number the web does not know yet).
 */
export function levelTitle(
  ladder: LevelLadder,
  level: number | null | undefined,
): string | null {
  if (
    typeof level !== "number" ||
    !Number.isInteger(level) ||
    level < 1 ||
    level > LADDER_SIZE[ladder]
  ) {
    return null;
  }

  return t(`levels.${ladder}Level${level}` as MessageKey);
}

/**
 * „Уште 2 рецензии и 15 поени до „Активен рецензент“.“ — only the parts
 * still missing; null at the top of the ladder.
 */
export function nextStepText(
  ladder: LevelLadder,
  next: MyLevels["reviews"]["next"] | MyLevels["forum"]["next"],
): string | null {
  if (next === null) {
    return null;
  }

  const title = levelTitle(ladder, next.level);

  if (title === null) {
    return null;
  }

  const missingCount =
    "missing_reviews" in next ? next.missing_reviews : next.missing_posts;
  const parts: string[] = [];

  if (missingCount > 0) {
    parts.push(
      tCount(
        ladder === "review" ? "levels.reviewsCount" : "levels.postsCount",
        missingCount,
      ),
    );
  }

  if (next.missing_points > 0) {
    parts.push(tCount("levels.pointsCount", next.missing_points));
  }

  if (parts.length === 0) {
    return null;
  }

  return tFormat("levels.nextStep", {
    missing: parts.join(t("levels.and")),
    title,
  });
}
