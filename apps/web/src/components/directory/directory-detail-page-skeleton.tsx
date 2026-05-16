import { PageShell } from "@/components/ui/page-shell";
import { Skeleton } from "@/components/ui/skeleton";

export function DirectoryDetailPageSkeleton() {
  return (
    <PageShell className="gap-8">
      <Skeleton className="h-4 w-64 max-w-full" />
      <div className="grid gap-8 lg:grid-cols-[1fr_320px]">
        <div className="space-y-6">
          <Skeleton className="h-64 w-full rounded-2xl" />
          <Skeleton className="h-32 w-full rounded-xl" />
          <Skeleton className="h-48 w-full rounded-xl" />
        </div>
        <Skeleton className="hidden h-80 rounded-2xl lg:block" />
      </div>
    </PageShell>
  );
}
