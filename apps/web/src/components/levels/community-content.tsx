import type { ReactNode } from "react";
import { ContributorLevel } from "@/components/levels/contributor-level";
import { TextLink } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import { Icon, type IconName } from "@/components/ui/icons";
import { Monogram } from "@/components/ui/user-avatar";
import type {
  ForumMemberRow,
  LevelLadder,
  LevelRulesInfo,
  Leaderboards,
  ReviewerRow,
} from "@/lib/api/levels";
import { levelTitle } from "@/lib/levels";
import { monthLabel } from "@/lib/review-integrity";
import { t, tCount, tFormat } from "@/i18n/t";

type Row = {
  username: string;
  level: number;
  stats: string;
};

/**
 * W8-C „Заедница“: last month's „Најкорисни рецензенти“ and „Најактивни во
 * форумот“ (top 10 by username), then how the titles are earned — the rules
 * come from the API, so the page always states the numbers it applies.
 */
export function CommunityContent({ boards }: { boards: Leaderboards | null }) {
  // The API sends no forum list while the forum module is off.
  const showForum = Boolean(boards?.forum);
  const month = boards ? monthLabel(boards.month) : null;

  return (
    <div className="mx-auto flex w-full max-w-[1240px] flex-col gap-10 px-5 pb-14 pt-4 lg:gap-14 lg:px-6 lg:pb-20 lg:pt-10">
      <section
        aria-labelledby="community-title"
        className="flex flex-col gap-3 rounded-sheet bg-apricot p-6 lg:p-14"
      >
        <p className="type-meta font-semibold text-ink">
          {t("levels.pageTitle")}
        </p>
        <h1 id="community-title" className="type-h1 text-ink">
          {t("levels.heroTitle")}
        </h1>
        <p className="measure type-reading text-ink">{t("levels.heroBody")}</p>
        <p className="measure flex items-start gap-2 type-body text-ink">
          <Icon name="shield-check" size={24} className="mt-0.5 shrink-0" />
          <span>{t("levels.heroNoPrizes")}</span>
        </p>
      </section>

      {boards === null ? (
        <Card tone="sand" padding="md">
          <p className="type-body text-ink">{t("levels.unavailable")}</p>
        </Card>
      ) : (
        <div className="grid gap-4 lg:grid-cols-2 lg:gap-6">
          <Board
            id="recenzenti"
            icon="star"
            title={t("levels.reviewersTitle")}
            hint={t("levels.reviewersHint")}
            month={month}
            empty={t("levels.emptyReviewers")}
            ladder="review"
            rows={boards.reviewers.map(reviewerRow)}
          />
          {showForum && boards.forum ? (
            <Board
              id="forum"
              icon="message-circle"
              title={t("levels.forumTitle")}
              hint={t("levels.forumHint")}
              month={month}
              empty={t("levels.emptyForum")}
              ladder="forum"
              rows={boards.forum.map(forumRow)}
              footer={
                <TextLink href="/forum" trailingIcon="arrow-right">
                  {t("levels.openForum")}
                </TextLink>
              }
            />
          ) : null}
        </div>
      )}

      {boards ? (
        <HowItWorks rules={boards.rules} showForum={showForum} />
      ) : null}
    </div>
  );
}

function reviewerRow(row: ReviewerRow): Row {
  return {
    username: row.username,
    level: row.level,
    stats: [
      tCount("levels.reviewsCount", row.reviews),
      tCount("levels.helpfulTimes", row.helpful),
    ].join(" · "),
  };
}

function forumRow(row: ForumMemberRow): Row {
  return {
    username: row.username,
    level: row.level,
    stats: [
      tCount("levels.postsCount", row.topics + row.replies),
      tCount("levels.helpfulTimes", row.helpful),
    ].join(" · "),
  };
}

function Board({
  id,
  icon,
  title,
  hint,
  month,
  empty,
  ladder,
  rows,
  footer,
}: {
  id: string;
  icon: IconName;
  title: string;
  hint: string;
  month: string | null;
  empty: string;
  ladder: LevelLadder;
  rows: Row[];
  footer?: ReactNode;
}) {
  const headingId = `community-${id}`;

  return (
    <Card
      as="section"
      id={id}
      aria-labelledby={headingId}
      edge
      className="flex flex-col gap-5"
    >
      <div className="flex items-start gap-3">
        <span className="inline-flex size-12 shrink-0 items-center justify-center rounded-full bg-apricot text-ink">
          <Icon name={icon} size={24} />
        </span>
        <div className="flex min-w-0 flex-col gap-1">
          <h2 id={headingId} className="type-h2 text-ink">
            {title}
          </h2>
          {month ? (
            <p className="type-meta font-semibold text-ink">
              {tFormat("levels.monthCaption", { month })}
            </p>
          ) : null}
          <p className="type-meta text-ink-2">{hint}</p>
        </div>
      </div>
      {rows.length === 0 ? (
        <p className="rounded-card bg-cream p-4 type-body text-ink-2">
          {empty}
        </p>
      ) : (
        <ol className="m-0 flex list-none flex-col p-0">
          {rows.map((row, index) => (
            <li
              key={row.username}
              className="flex items-center gap-3 border-t border-line py-3 first:border-t-0 first:pt-0"
            >
              {/* aria-label is not read on a plain span: the rank is
                  spoken from visually hidden text instead. */}
              <span
                className="inline-flex size-9 shrink-0 items-center justify-center rounded-full bg-chip-tint font-ui text-[0.9375rem] font-semibold tabular-nums text-ink"
                aria-hidden="true"
              >
                {index + 1}
              </span>
              <span className="sr-only">
                {tFormat("levels.rankLabel", { rank: index + 1 })}
              </span>
              <Monogram name={row.username} size={40} />
              <div className="flex min-w-0 flex-1 flex-col gap-1">
                <p className="flex flex-wrap items-center gap-x-2 gap-y-1 type-body font-semibold text-ink">
                  <span className="break-all">{row.username}</span>
                  <ContributorLevel ladder={ladder} level={row.level} />
                </p>
                <p className="type-meta text-ink-2">{row.stats}</p>
              </div>
            </li>
          ))}
        </ol>
      )}
      {footer ? <div className="mt-auto">{footer}</div> : null}
    </Card>
  );
}

function HowItWorks({
  rules,
  showForum,
}: {
  rules: LevelRulesInfo;
  showForum: boolean;
}) {
  const ruleLines = [
    tFormat("levels.ruleReview", {
      points: rules.reviews.review_points,
      perDay: rules.reviews.reviews_per_day,
    }),
    tFormat("levels.ruleReviewHelpful", {
      points: rules.reviews.helpful_points,
    }),
    ...(showForum
      ? [
          tFormat("levels.ruleTopic", {
            points: rules.forum.topic_points,
            perDay: rules.forum.topics_per_day,
          }),
          tFormat("levels.ruleReply", {
            points: rules.forum.reply_points,
            perDay: rules.forum.replies_per_day,
          }),
          tFormat("levels.ruleReplyHelpful", {
            points: rules.forum.helpful_points,
          }),
        ]
      : []),
    tFormat("levels.ruleCaps", {
      perVoter: rules.helpful_per_voter_per_author,
      perItem: rules.helpful_per_item,
    }),
    tFormat("levels.ruleRemoved", {
      review: rules.reviews.removed_penalty,
      forum: rules.forum.removed_penalty,
    }),
    t("levels.ruleIgnored"),
  ];

  return (
    <section
      id="zvanja"
      aria-labelledby="community-how"
      className="flex flex-col gap-4 lg:gap-5"
    >
      <div className="flex flex-col gap-1">
        <h2 id="community-how" className="type-h2 text-ink">
          {t("levels.howTitle")}
        </h2>
        <p className="measure type-body text-ink-2">{t("levels.howIntro")}</p>
      </div>
      <Card padding="md">
        <ul className="m-0 flex list-none flex-col gap-3 p-0">
          {ruleLines.map((line) => (
            <li key={line} className="flex items-start gap-3">
              <Icon name="check" size={20} className="mt-1 shrink-0 text-ink" />
              <span className="type-body text-ink">{line}</span>
            </li>
          ))}
        </ul>
      </Card>
      <div className="grid gap-4 lg:grid-cols-2 lg:gap-6">
        <LadderTable
          id="ladder-reviews"
          title={t("levels.ladderReviewsTitle")}
          rows={rules.reviews.ladder.map((step) => ({
            title: levelTitle("review", step.level),
            needs: tFormat("levels.ladderReviewNeeds", {
              reviews: tCount("levels.reviewsCount", step.reviews),
              points: tCount("levels.pointsCount", step.points),
            }),
          }))}
        />
        {showForum ? (
          <LadderTable
            id="ladder-forum"
            title={t("levels.ladderForumTitle")}
            note={t("levels.ladderForumNote")}
            rows={rules.forum.ladder.map((step) => ({
              title: levelTitle("forum", step.level),
              needs: tFormat("levels.ladderForumNeeds", {
                posts: tCount("levels.postsCount", step.posts),
                points: tCount("levels.pointsCount", step.points),
              }),
            }))}
          />
        ) : null}
      </div>
    </section>
  );
}

function LadderTable({
  id,
  title,
  note,
  rows,
}: {
  id: string;
  title: string;
  note?: string;
  rows: Array<{ title: string | null; needs: string }>;
}) {
  return (
    <Card
      as="section"
      aria-labelledby={id}
      padding="md"
      className="flex flex-col gap-3"
    >
      <h3 id={id} className="type-h3 text-ink">
        {title}
      </h3>
      <table className="w-full border-collapse text-left">
        <thead>
          <tr className="type-meta text-ink-2">
            <th scope="col" className="pb-2 pr-4 font-semibold">
              {t("levels.ladderTitle")}
            </th>
            <th scope="col" className="pb-2 font-semibold">
              {t("levels.ladderNeeds")}
            </th>
          </tr>
        </thead>
        <tbody>
          {rows.map((row) =>
            row.title ? (
              <tr key={row.title} className="border-t border-line">
                <th scope="row" className="py-2.5 pr-4 font-normal">
                  <span className="type-body font-semibold text-ink">
                    {row.title}
                  </span>
                </th>
                <td className="py-2.5 type-body text-ink">{row.needs}</td>
              </tr>
            ) : null,
          )}
        </tbody>
      </table>
      {note ? <p className="type-meta text-ink-2">{note}</p> : null}
    </Card>
  );
}
