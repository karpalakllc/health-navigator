import Link from "next/link";
import { Button } from "@/components/ui/button";
import { SectionHeader } from "@/components/ui/section-header";
import { Tag } from "@/components/ui/tag";
import type { ForumTopicSearchItem } from "@/lib/api/forum";
import { cn } from "@/lib/cn";
import { forumAnswerHref } from "@/lib/forum/answer-link";
import { formatForumLastActivity } from "@/lib/format";
import { t, tFormat } from "@/i18n/t";

/** Shown at most; phones get the same three, stacked. */
export const HOME_UNANSWERED_LIMIT = 3;

/**
 * „Помогнете некому“: up to three forum questions still waiting for a first
 * answer (GET /forum/topics/unanswered), right after the community band.
 * Topics the band already shows are skipped, so nothing is listed twice; with
 * none left the section is not rendered at all.
 *
 * Each white card: category tag, the question (a link to the topic), who asks
 * and when, and an „Одговори“ button straight to the topic's reply box.
 */
export function HomeUnanswered({
  topics,
  exclude = [],
  className,
}: {
  topics: ForumTopicSearchItem[];
  /** Topics already on the page ("category/slug"). */
  exclude?: string[];
  className?: string;
}) {
  const skip = new Set(exclude);
  const shown = topics
    .filter((topic) => !skip.has(`${topic.category.slug}/${topic.slug}`))
    .slice(0, HOME_UNANSWERED_LIMIT);

  if (shown.length === 0) {
    return null;
  }

  return (
    <section
      aria-labelledby="home-unanswered-title"
      data-track="home-help"
      className={className}
    >
      <SectionHeader
        id="home-unanswered-title"
        title={t("help.homeTitle")}
        description={t("help.homeLead")}
        action={{ href: "/forum?view=unanswered", label: t("help.homeAll") }}
      />
      <ul
        className={cn(
          "mt-3 grid gap-3 md:grid-cols-2 lg:mt-6 lg:gap-6",
          shown.length >= 3 && "lg:grid-cols-3",
        )}
      >
        {shown.map((topic, index) => {
          const asked = topic.published_at ?? topic.last_post_at;

          return (
            <li
              key={`${topic.category.slug}/${topic.slug}`}
              // Two columns on tablets: whole rows only.
              className={cn("flex", index === 2 && "md:max-lg:hidden")}
            >
              <article className="card hover-lift flex w-full flex-col gap-3 p-4 lg:p-5">
                <div>
                  <Tag>{topic.category.name}</Tag>
                </div>
                <h3 className="font-ui text-lg font-semibold leading-6 text-ink lg:text-[1.1875rem]">
                  <Link
                    href={`/forum/${topic.category.slug}/${topic.slug}`}
                    className="link-grow line-clamp-3"
                  >
                    {topic.title}
                  </Link>
                </h3>
                <p className="type-meta flex-1 text-ink-2">
                  {tFormat("help.askedBy", { name: topic.author_name })}
                  {asked ? (
                    <>
                      <span aria-hidden="true"> · </span>
                      <span className="sr-only">, </span>
                      <time dateTime={asked}>
                        {formatForumLastActivity(asked)}
                      </time>
                    </>
                  ) : null}
                </p>
                <div>
                  <Button
                    href={forumAnswerHref(topic.category.slug, topic.slug)}
                    variant="secondary"
                    leadingIcon="reply"
                    // Starts with the visible label (WCAG 2.5.3), then names the topic.
                    aria-label={`${t("help.answer")} ${tFormat("help.answerTopic", { title: topic.title })}`}
                  >
                    {t("help.answer")}
                  </Button>
                </div>
              </article>
            </li>
          );
        })}
      </ul>
    </section>
  );
}
