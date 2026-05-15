import { Skeleton } from "@/components/ui/skeleton";

/** Login form placeholder for Suspense / loading route. */
export function LoginFormSkeleton() {
  return (
    <div className="grid gap-3 rounded-2xl border border-border bg-card p-5">
      <div className="grid gap-2">
        <Skeleton className="h-4 w-16" />
        <Skeleton className="h-10 w-full rounded-xl" />
      </div>
      <div className="grid gap-2">
        <Skeleton className="h-4 w-20" />
        <Skeleton className="h-10 w-full rounded-xl" />
      </div>
      <Skeleton className="h-10 w-full rounded-xl sm:w-40" />
      <Skeleton className="h-3 w-full max-w-sm" />
    </div>
  );
}
