import { Badge } from "@/components/ui/badge";
import { Card } from "@/components/ui/card";
import type { ForumAuthor } from "@/lib/api/forum";
import { authorInitials, formatForumDateTime } from "@/lib/format";
import { t } from "@/i18n/t";

type ForumAuthorCardProps = {
  author: ForumAuthor;
  variant?: "sidebar" | "inline";
};

function AuthorStats({
  author,
  className,
}: {
  author: ForumAuthor;
  className?: string;
}) {
  return (
    <dl className={className}>
      <div>
        <dt className="font-medium text-foreground/80">
          {t("forum.authorTopics")}
        </dt>
        <dd className="tabular-nums">{author.topics_count}</dd>
      </div>
      <div>
        <dt className="font-medium text-foreground/80">
          {t("forum.authorPosts")}
        </dt>
        <dd className="tabular-nums">{author.posts_count}</dd>
      </div>
      {author.member_since ? (
        <div className="col-span-2 lg:col-span-1">
          <dt className="font-medium text-foreground/80">
            {t("forum.authorMemberSince")}
          </dt>
          <dd>{formatForumDateTime(author.member_since)}</dd>
        </div>
      ) : null}
    </dl>
  );
}

export function ForumAuthorCard({
  author,
  variant = "sidebar",
}: ForumAuthorCardProps) {
  const isSidebar = variant === "sidebar";

  return (
    <Card
      className={
        isSidebar
          ? "h-full border-0 bg-muted/30 p-4 shadow-none ring-0 lg:rounded-none lg:bg-transparent lg:p-0"
          : "border-border/80 bg-muted/20 p-3"
      }
    >
      <div
        className={
          isSidebar
            ? "flex flex-row items-center gap-3 lg:flex-col lg:items-center lg:text-center"
            : "flex items-center gap-3"
        }
      >
        <span
          className={
            isSidebar
              ? "flex size-14 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-primary/15 to-accent/15 text-base font-bold text-primary ring-1 ring-primary/15 lg:size-20 lg:text-xl"
              : "flex size-11 shrink-0 items-center justify-center rounded-full bg-muted text-sm font-semibold text-foreground"
          }
          aria-hidden
        >
          {authorInitials(author.name)}
        </span>

        <div
          className={
            isSidebar ? "min-w-0 flex-1 space-y-2 lg:w-full" : "min-w-0 flex-1"
          }
        >
          <div className="space-y-1">
            <p className="font-semibold text-foreground">{author.name}</p>
            {author.is_team_member ? (
              <Badge variant="primary" className="text-[10px]">
                {t("forum.authorTeam")}
              </Badge>
            ) : author.is_forum_moderator === true ? (
              <Badge variant="primary" className="text-[10px]">
                {t("forum.authorForumModerator")}
              </Badge>
            ) : (
              <Badge variant="outline" className="text-[10px]">
                {t("forum.authorMember")}
              </Badge>
            )}
          </div>

          {isSidebar ? (
            <AuthorStats
              author={author}
              className="hidden space-y-2 text-xs text-muted-foreground lg:grid lg:gap-2"
            />
          ) : (
            <AuthorStats
              author={author}
              className="mt-2 grid grid-cols-2 gap-x-3 gap-y-1 text-xs text-muted-foreground"
            />
          )}
        </div>
      </div>

      {isSidebar ? (
        <AuthorStats
          author={author}
          className="mt-3 grid grid-cols-2 gap-2 border-t border-border/80 pt-3 text-xs text-muted-foreground lg:hidden"
        />
      ) : null}
    </Card>
  );
}
