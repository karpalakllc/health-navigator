import { PageShell } from "@/components/ui/page-shell";
import { Skeleton } from "@/components/ui/skeleton";

export function GuidancePageSkeleton() {
  return (
    <PageShell>
      <div className="space-y-2">
        <Skeleton className="h-9 w-72 max-w-[90%]" />
        <Skeleton className="h-5 w-full max-w-xl" />
      </div>
      <Skeleton className="h-40 w-full rounded-2xl" />
      <Skeleton className="h-12 w-full max-w-md rounded-xl" />
      <div className="flex gap-3">
        <Skeleton className="h-10 w-36 rounded-xl" />
        <Skeleton className="h-10 w-48 rounded-xl" />
      </div>
    </PageShell>
  );
}
