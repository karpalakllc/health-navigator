import { PageShell } from "@/components/ui/page-shell";
import { Skeleton } from "@/components/ui/skeleton";

/** Loading placeholder for directory list routes (doctors, facilities, pharmacies, products). */
export function DirectoryListPageSkeleton() {
  return (
    <PageShell>
      <div className="space-y-2">
        <Skeleton className="h-9 w-64 max-w-[85%]" />
        <Skeleton className="h-5 w-full max-w-xl" />
      </div>
      <Skeleton className="h-36 w-full rounded-2xl" />
      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        {Array.from({ length: 6 }).map((_, index) => (
          <Skeleton key={index} className="h-48 rounded-2xl" />
        ))}
      </div>
    </PageShell>
  );
}
