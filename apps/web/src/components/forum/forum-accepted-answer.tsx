import { TextLink } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import { Icon } from "@/components/ui/icons";
import type { ForumAcceptedAnswer } from "@/lib/api/forum";
import { safePath } from "@/lib/notification-text";
import { t, tFormat } from "@/i18n/t";

/**
 * „Најкорисен одговор“ under the opening post: an excerpt, who wrote it, and
 * a link to the full reply (which may be on another page of the thread).
 */
export function ForumAcceptedAnswerCard({
  answer,
}: {
  answer: ForumAcceptedAnswer;
}) {
  const href = safePath(answer.path) ?? `#post-${answer.id}`;

  return (
    <Card
      as="section"
      padding="none"
      aria-labelledby="forum-accepted-heading"
      className="flex flex-col gap-3 border-2 border-care p-5 lg:px-8 lg:py-6"
    >
      <h2
        id="forum-accepted-heading"
        className="flex items-center gap-2 type-h3 text-care"
      >
        <Icon name="award" size={22} />
        {t("forum.acceptedAnswer")}
      </h2>
      <p className="type-meta text-ink-2">
        {tFormat("forum.acceptedAnswerBy", { name: answer.author_name })}
      </p>
      <p className="measure line-clamp-4 whitespace-pre-wrap break-words type-reading text-ink">
        {answer.excerpt}
      </p>
      <TextLink href={href}>{t("forum.acceptedAnswerOpen")}</TextLink>
    </Card>
  );
}
