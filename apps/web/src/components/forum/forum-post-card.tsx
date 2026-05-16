import { Card } from "@/components/ui/card";
import { ForumAuthorCard } from "@/components/forum/forum-author-card";
import type { ForumAuthor, ForumPost } from "@/lib/api/forum";
import { formatForumDateTime } from "@/lib/format";
import { t } from "@/i18n/t";

type ForumPostCardProps = {
  post: ForumPost;
  isOriginalPost?: boolean;
};

function resolveAuthor(post: ForumPost): ForumAuthor {
  return (
    post.author ?? {
      name: post.author_name,
      member_since: null,
      topics_count: 0,
      posts_count: 0,
      is_team_member: false,
    }
  );
}

export function ForumPostCard({ post, isOriginalPost = false }: ForumPostCardProps) {
  const author = resolveAuthor(post);

  return (
    <Card
      className={
        isOriginalPost
          ? "overflow-hidden border-primary/20 bg-gradient-to-br from-primary/5 via-card to-card"
          : "overflow-hidden"
      }
    >
      <article className="flex flex-col lg:flex-row">
        <aside className="border-b border-border/80 bg-muted/20 lg:w-52 lg:shrink-0 lg:border-b-0 lg:border-r lg:bg-muted/15 xl:w-56">
          <ForumAuthorCard author={author} variant="sidebar" />
        </aside>

        <div className="min-w-0 flex-1 p-4 sm:p-5 lg:p-6">
          <header className="mb-3 flex flex-wrap items-start justify-between gap-2 border-b border-border/60 pb-3">
            <div className="min-w-0 space-y-0.5">
              <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">
                {isOriginalPost ? t("forum.originalPost") : t("forum.reply")}
              </p>
              {!isOriginalPost ? (
                <p className="text-sm font-medium text-foreground lg:hidden">{author.name}</p>
              ) : null}
            </div>
            {post.published_at ? (
              <time
                dateTime={post.published_at}
                className="shrink-0 text-xs text-muted-foreground"
              >
                {formatForumDateTime(post.published_at)}
              </time>
            ) : null}
          </header>
          <p className="whitespace-pre-wrap text-sm leading-relaxed text-foreground sm:text-base">
            {post.body}
          </p>
        </div>
      </article>
    </Card>
  );
}
