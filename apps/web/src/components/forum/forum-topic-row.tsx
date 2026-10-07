import Link from "next/link";
import { Fragment, type ReactNode } from "react";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import { Tag } from "@/components/ui/tag";
import { formatForumLastActivity } from "@/lib/format";
import { cn } from "@/lib/cn";
import { t, tCount, tFormat } from "@/i18n/t";

export type ForumTopicRowData = {
  href: string;
  title: string;
  categoryName?: string;
  authorName: string;
  repliesCount: number;
  lastActivityAt: string | null;
  isPinned?: boolean;
  isLocked?: boolean;
  /**
   * „Прашања без одговор“: an „Одговори“ button to the topic's reply box
   * (forumAnswerHref) in place of the reply count.
   */
  answerHref?: string;
};

/**
 * Compact topic rows in one white card: title (a link that covers the row),
 * a meta line „категорија · Ана М. · пред 2 дена“, and on the right the reply
 * count („4 одговори“) or a „Без одговор“ tag.
 */
export function ForumTopicList({
  topics,
  headingLevel = 3,
  label,
  className,
}: {
  topics: ForumTopicRowData[];
  headingLevel?: 2 | 3;
  /** Accessible name of the list (e.g. the section heading's text). */
  label?: string;
  className?: string;
}) {
  return (
    <Card padding="none" className={cn("overflow-hidden", className)}>
      <ul aria-label={label}>
        {topics.map((topic) => (
          <ForumTopicRow
            key={topic.href}
            topic={topic}
            headingLevel={headingLevel}
          />
        ))}
      </ul>
    </Card>
  );
}

export function ForumTopicRow({
  topic,
  headingLevel = 3,
}: {
  topic: ForumTopicRowData;
  headingLevel?: 2 | 3;
}) {
  const Heading = headingLevel === 2 ? "h2" : "h3";
  const meta: ReactNode[] = [
    topic.categoryName,
    topic.authorName,
    topic.lastActivityAt ? (
      <time dateTime={topic.lastActivityAt}>
        {formatForumLastActivity(topic.lastActivityAt)}
      </time>
    ) : null,
  ].filter(Boolean);

  return (
    <li
      data-track="forum-topic"
      className={cn(
        "relative flex min-h-[4.5rem] items-start gap-4 border-t border-line px-4 py-4 first:border-t-0 transition-colors hover:bg-cream sm:px-5",
        // Phones: the answer button goes under the title, not beside it.
        topic.answerHref && "max-sm:flex-col max-sm:gap-3",
      )}
    >
      <div className="flex min-w-0 flex-1 flex-col gap-1">
        {topic.isPinned || topic.isLocked ? (
          <div className="flex flex-wrap gap-2">
            {topic.isPinned ? <Tag tone="ink">{t("forum.pinned")}</Tag> : null}
            {topic.isLocked ? <Tag>{t("forum.locked")}</Tag> : null}
          </div>
        ) : null}
        <Heading className="line-clamp-2 font-ui text-[1.1875rem] leading-[1.625rem] font-semibold text-ink">
          <Link
            href={topic.href}
            className="link-grow after:absolute after:inset-0 after:content-['']"
          >
            {topic.title}
          </Link>
        </Heading>
        <p className="type-meta text-ink-2">
          {meta.map((part, index) => (
            <Fragment key={index}>
              {index > 0 ? <span aria-hidden="true"> · </span> : null}
              {part}
            </Fragment>
          ))}
        </p>
      </div>
      {topic.answerHref ? (
        // Above the stretched title link, so both stay clickable.
        <Button
          href={topic.answerHref}
          variant="secondary"
          size="sm"
          leadingIcon="reply"
          // Starts with the visible label (WCAG 2.5.3), then names the topic.
          aria-label={`${t("help.answer")} ${tFormat("help.answerTopic", { title: topic.title })}`}
          className="relative z-10 mt-0.5 shrink-0"
        >
          {t("help.answer")}
        </Button>
      ) : (
        <ForumReplyCount count={topic.repliesCount} />
      )}
    </li>
  );
}

/** „4 / одговори“ as a figure, or the „Без одговор“ tag for zero. */
export function ForumReplyCount({ count }: { count: number }) {
  if (count === 0) {
    return (
      <Tag tone="tint" className="mt-0.5 shrink-0">
        {t("forum.unanswered")}
      </Tag>
    );
  }

  return (
    <p className="flex shrink-0 flex-col items-end text-right">
      <span className="font-ui text-xl leading-6 font-bold text-ink tabular-nums">
        {count}
      </span>{" "}
      <span className="type-meta text-ink-2">
        {tCount("forum.repliesLabel", count)}
      </span>
    </p>
  );
}
