import { PageShell } from "@/components/ui/page-shell";
import { Skeleton } from "@/components/ui/skeleton";

export default function Loading() {
  return (
    <PageShell>
      <div className="flex flex-col gap-8 lg:flex-row lg:items-start">
        <div className="flex gap-1 rounded-xl border border-border bg-card p-1 lg:w-52 lg:flex-col">
          <Skeleton className="h-10 flex-1 rounded-lg lg:flex-none" />
          <Skeleton className="h-10 flex-1 rounded-lg lg:flex-none" />
          <Skeleton className="h-10 flex-1 rounded-lg lg:flex-none" />
        </div>
        <div className="min-w-0 flex-1 space-y-6">
          <div className="space-y-2">
            <Skeleton className="h-9 w-56 max-w-[90%]" />
            <Skeleton className="h-5 w-full max-w-lg" />
          </div>
          <Skeleton className="h-44 w-full rounded-2xl" />
          <div className="grid gap-4 sm:grid-cols-2">
            <Skeleton className="h-28 rounded-2xl" />
            <Skeleton className="h-28 rounded-2xl" />
          </div>
        </div>
      </div>
    </PageShell>
  );
}
