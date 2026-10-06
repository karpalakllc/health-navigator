import { Skeleton } from "@/components/ui/skeleton";
import { forumPageClass } from "@/components/forum/forum-layout";
import { cn } from "@/lib/cn";

const shell = forumPageClass;

function RowsCard({ rows }: { rows: number }) {
  return (
    <div className="card overflow-hidden">
      {Array.from({ length: rows }).map((_, i) => (
        <div
          key={i}
          className="flex items-start gap-4 border-t border-line px-5 py-4 first:border-t-0"
        >
          <div className="flex flex-1 flex-col gap-2">
            <Skeleton className="h-5 w-4/5" />
            <Skeleton className="h-4 w-1/2" />
          </div>
          <Skeleton className="h-10 w-14" />
        </div>
      ))}
    </div>
  );
}

function TwoColumns({
  main,
  aside,
}: {
  main: React.ReactNode;
  aside?: boolean;
}) {
  return (
    <div className="grid items-start gap-8 lg:grid-cols-[minmax(0,8fr)_minmax(0,4fr)]">
      <div className="flex min-w-0 flex-col gap-6">{main}</div>
      {aside ? (
        <div className="flex flex-col gap-5">
          <Skeleton className="h-64 w-full rounded-card" />
          <Skeleton className="h-48 w-full rounded-card" />
        </div>
      ) : null}
    </div>
  );
}

/** Forum home: title, actions, safety note, search, topic rows. */
export function ForumListPageSkeleton() {
  return (
    <div className={cn(shell, "gap-6")}>
      <Skeleton className="h-10 w-48" />
      <Skeleton className="h-5 w-full max-w-xl" />
      <Skeleton className="h-14 w-full max-w-sm rounded-pill" />
      <TwoColumns
        aside
        main={
          <>
            <Skeleton className="h-20 w-full rounded-card" />
            <Skeleton className="h-36 w-full rounded-card" />
            <RowsCard rows={5} />
          </>
        }
      />
    </div>
  );
}

/** Category: back pill, title, sort chips, topic rows. */
export function ForumCategoryPageSkeleton() {
  return (
    <div className={cn(shell, "gap-6")}>
      <Skeleton className="h-11 w-32 rounded-pill" />
      <Skeleton className="h-10 w-2/3 max-w-md" />
      <TwoColumns
        aside
        main={
          <>
            <Skeleton className="h-20 w-full rounded-card" />
            <div className="flex gap-2">
              <Skeleton className="h-11 w-28 rounded-pill" />
              <Skeleton className="h-11 w-32 rounded-pill" />
            </div>
            <RowsCard rows={6} />
          </>
        }
      />
    </div>
  );
}

/** Thread: back pill, title, safety note, posts. */
export function ForumTopicPageSkeleton() {
  return (
    <div className={cn(shell, "gap-6")}>
      <TwoColumns
        aside
        main={
          <>
            <Skeleton className="h-11 w-40 rounded-pill" />
            <Skeleton className="h-10 w-full max-w-2xl" />
            <Skeleton className="h-5 w-56" />
            <Skeleton className="h-20 w-full rounded-card" />
            {Array.from({ length: 3 }).map((_, i) => (
              <Skeleton key={i} className="h-48 w-full rounded-card" />
            ))}
          </>
        }
      />
    </div>
  );
}
