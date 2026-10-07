import { ContributorLevel } from "@/components/levels/contributor-level";
import { TextLink } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import { Icon, type IconName } from "@/components/ui/icons";
import { SectionHeader } from "@/components/ui/section-header";
import type { LevelLadder, MyLevels } from "@/lib/api/levels";
import { LADDER_SIZE, nextStepText } from "@/lib/levels";
import { t, tCount, type MessageKey } from "@/i18n/t";

/**
 * W8-C: the member's own levels on the account overview — the title, points,
 * what they rest on, and the next step („Уште 2 рецензии до …“), for
 * reviews and for the forum. Nothing here is public except the title.
 */
export function AccountLevelProgress({ levels }: { levels: MyLevels }) {
  const reviewStats = [
    tCount("levels.reviewsCount", levels.reviews.reviews),
    tCount("levels.helpfulTimes", levels.reviews.helpful),
  ];
  const forumStats = [
    tCount("levels.postsCount", levels.forum.topics + levels.forum.replies),
    tCount("levels.helpfulTimes", levels.forum.helpful),
  ];

  return (
    <Card as="section" aria-labelledby="account-levels">
      <div className="flex flex-col gap-6">
        <SectionHeader
          id="account-levels"
          title={t("levels.progressTitle")}
          level={2}
          description={t("levels.progressHint")}
        />
        <ul className="grid gap-4 sm:grid-cols-2">
          <LadderProgress
            ladder="review"
            icon="star"
            heading="levels.progressReviews"
            level={levels.reviews.level}
            points={levels.reviews.points}
            stats={reviewStats}
            next={nextStepText("review", levels.reviews.next)}
          />
          <LadderProgress
            ladder="forum"
            icon="message-circle"
            heading="levels.progressForum"
            level={levels.forum.level}
            points={levels.forum.points}
            stats={forumStats}
            next={nextStepText("forum", levels.forum.next)}
          />
        </ul>
        {levels.shown_publicly ? null : (
          <p className="type-meta text-ink-2">{t("levels.notShown")}</p>
        )}
        <TextLink
          href="/community#zvanja"
          trailingIcon="arrow-right"
          className="self-start"
        >
          {t("levels.howLink")}
        </TextLink>
      </div>
    </Card>
  );
}

function LadderProgress({
  ladder,
  icon,
  heading,
  level,
  points,
  stats,
  next,
}: {
  ladder: LevelLadder;
  icon: IconName;
  heading: MessageKey;
  level: number;
  points: number;
  stats: string[];
  next: string | null;
}) {
  const atTop = level >= LADDER_SIZE[ladder];

  return (
    <li className="flex flex-col gap-3 rounded-card bg-cream p-5">
      <div className="flex items-center gap-3">
        <span className="inline-flex size-10 shrink-0 items-center justify-center rounded-full bg-apricot text-ink">
          <Icon name={icon} size={20} />
        </span>
        <h3 className="type-h3 text-ink">{t(heading)}</h3>
      </div>
      <div className="flex flex-wrap items-center gap-2">
        {level > 0 ? (
          <ContributorLevel ladder={ladder} level={level} />
        ) : (
          <span className="type-body text-ink-2">{t("levels.noLevelYet")}</span>
        )}
        <span className="type-meta font-semibold text-ink">
          {tCount("levels.pointsCount", points)}
        </span>
      </div>
      <p className="type-meta text-ink-2">{stats.join(" · ")}</p>
      {atTop ? (
        <p className="type-body text-ink">{t("levels.topLevel")}</p>
      ) : next ? (
        <p className="type-body text-ink">{next}</p>
      ) : null}
    </li>
  );
}
