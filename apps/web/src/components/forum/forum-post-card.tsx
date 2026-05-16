import { Card } from "@/components/ui/card";
import type { ForumPost } from "@/lib/api/forum";
import { authorInitials, formatForumDateTime } from "@/lib/format";

type ForumPostCardProps = {
  post: ForumPost;
  isOriginalPost?: boolean;
};

export function ForumPostCard({ post, isOriginalPost = false }: ForumPostCardProps) {
  return (
    <Card
      className={
        isOriginalPost
          ? "border-primary/20 bg-gradient-to-br from-primary/5 via-card to-card p-5"
          : "p-4 sm:p-5"
      }
    >
      <div className="flex gap-3 sm:gap-4">
        <span
          className="flex size-10 shrink-0 items-center justify-center rounded-full bg-muted text-xs font-semibold text-foreground"
          aria-hidden
        >
          {authorInitials(post.author_name)}
        </span>
        <div className="min-w-0 flex-1 space-y-2">
          <div className="flex flex-wrap items-baseline gap-x-2 gap-y-0.5">
            <p className="text-sm font-semibold text-foreground">{post.author_name}</p>
            {post.published_at ? (
              <time dateTime={post.published_at} className="text-xs text-muted-foreground">
                {formatForumDateTime(post.published_at)}
              </time>
            ) : null}
          </div>
          <p className="whitespace-pre-wrap text-sm leading-relaxed text-foreground">{post.body}</p>
        </div>
      </div>
    </Card>
  );
}
