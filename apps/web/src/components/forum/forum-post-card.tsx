import type { ReactNode } from "react";
import { ForumPostBody } from "@/components/forum/forum-post-body";
import { ForumProfileCards } from "@/components/forum/forum-profile-cards";
import { ContributorLevel } from "@/components/levels/contributor-level";
import { Card } from "@/components/ui/card";
import { Tag, VerifiedTag } from "@/components/ui/tag";
import { Monogram } from "@/components/ui/user-avatar";
import type { ForumAuthor, ForumPost } from "@/lib/api/forum";
import { cn } from "@/lib/cn";
import {
  formatForumAuthorStats,
  formatForumDateTime,
  formatForumLastActivity,
} from "@/lib/format";
import { t } from "@/i18n/t";

type ForumPostCardProps = {
  post: ForumPost;
  isOriginalPost?: boolean;
  /**
   * Posted by the person who opened the topic: shows the „Автор“ tag. From
   * the API's `is_topic_author` (display names are not unique).
   */
  isTopicAuthor?: boolean;
  /** Post actions (e.g. „Одговори“), rendered under the body. */
  actions?: ReactNode;
  /** Marked „Најкорисен одговор“. */
  isAccepted?: boolean;
  /** Extra header tags (the device-only „Ново“ marker). */
  headerTags?: ReactNode;
};

function resolveAuthor(post: ForumPost): ForumAuthor {
  return (
    post.author ?? {
      name: post.author_name,
      member_since: null,
      topics_count: 0,
      posts_count: 0,
      is_team_member: false,
      is_forum_moderator: false,
    }
  );
}

/** Staff (the Здравје360 team) and forum moderators get the care highlight. */
export function staffLabel(author: ForumAuthor): string | null {
  if (author.is_team_member) {
    return t("forum.authorTeam");
  }

  if (author.is_forum_moderator === true) {
    return t("forum.authorForumModerator");
  }

  return null;
}

/**
 * One post: a white card with a monogram header (name, „Автор“ / staff tag,
 * relative time), the body in the reading face, then the actions. The opening
 * post carries the coral top edge; staff replies a 1px care-green border.
 */
export function ForumPostCard({
  post,
  isOriginalPost = false,
  isTopicAuthor = false,
  actions,
  isAccepted = false,
  headerTags,
}: ForumPostCardProps) {
  const author = resolveAuthor(post);
  const staff = staffLabel(author);
  const stats = formatForumAuthorStats(author);

  return (
    <Card
      as="article"
      edge={isOriginalPost}
      padding="none"
      data-staff={staff ? "true" : undefined}
      className={cn(
        "flex flex-col gap-4 p-5 lg:px-8 lg:py-7",
        staff && "border border-care",
        isAccepted && "border-2 border-care",
      )}
    >
      <header className="flex items-start gap-3">
        <Monogram name={author.name} size={44} />
        <div className="flex min-w-0 flex-1 flex-col gap-0.5">
          <div className="flex flex-wrap items-center gap-x-2 gap-y-1">
            <p className="font-ui text-[1.0625rem] leading-6 font-semibold text-ink">
              {isOriginalPost ? (
                <span className="sr-only">{t("forum.originalPost")}: </span>
              ) : null}
              {author.name}
            </p>
            {isTopicAuthor ? <Tag>{t("forum.authorBadge")}</Tag> : null}
            <ContributorLevel ladder="forum" level={author.level} />
            {staff ? <VerifiedTag>{staff}</VerifiedTag> : null}
            {isAccepted ? (
              <Tag tone="care" icon="award">
                {t("forum.acceptedAnswer")}
              </Tag>
            ) : null}
            {headerTags}
          </div>
          {stats ? <p className="type-meta text-ink-2">{stats}</p> : null}
          {post.published_at ? (
            <p className="type-meta text-ink-2">
              <time
                dateTime={post.published_at}
                title={formatForumDateTime(post.published_at)}
              >
                {formatForumLastActivity(post.published_at)}
              </time>
            </p>
          ) : null}
        </div>
      </header>
      <ForumPostBody body={post.body} />
      <ForumProfileCards cards={post.profile_cards} />
      {actions ? <div className="flex flex-wrap gap-2">{actions}</div> : null}
    </Card>
  );
}
