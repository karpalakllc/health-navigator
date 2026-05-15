import { PageShell } from "@/components/ui/page-shell";
import { Skeleton } from "@/components/ui/skeleton";

/** Forum index or category topic list. */
export function ForumListPageSkeleton() {
  return (
    <PageShell>
      <Skeleton className="h-9 w-48 max-w-[90%]" />
      <Skeleton className="h-5 w-full max-w-xl" />
      <Skeleton className="h-24 w-full rounded-2xl" />
      <div className="grid gap-3 sm:grid-cols-2">
        {Array.from({ length: 4 }).map((_, i) => (
          <Skeleton key={i} className="h-32 rounded-2xl" />
        ))}
      </div>
    </PageShell>
  );
}

/** Forum category with filter bar + topic rows. */
export function ForumCategoryPageSkeleton() {
  return (
    <PageShell>
      <Skeleton className="h-4 w-32" />
      <Skeleton className="h-9 w-2/3 max-w-md" />
      <Skeleton className="h-20 w-full rounded-2xl" />
      <Skeleton className="h-36 w-full rounded-2xl" />
      <div className="space-y-3">
        {Array.from({ length: 5 }).map((_, i) => (
          <Skeleton key={i} className="h-24 w-full rounded-xl" />
        ))}
      </div>
    </PageShell>
  );
}

/** Thread view. */
export function ForumTopicPageSkeleton() {
  return (
    <PageShell className="gap-6">
      <Skeleton className="h-4 w-56" />
      <Skeleton className="h-10 w-full max-w-2xl" />
      <Skeleton className="h-20 w-full rounded-2xl" />
      <Skeleton className="min-h-[120px] w-full rounded-xl" />
      <Skeleton className="h-8 w-32" />
      <div className="space-y-3">
        {Array.from({ length: 3 }).map((_, i) => (
          <Skeleton key={i} className="h-20 w-full rounded-xl" />
        ))}
      </div>
    </PageShell>
  );
}
