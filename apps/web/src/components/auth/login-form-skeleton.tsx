import { Skeleton } from "@/components/ui/skeleton";

/** Login form placeholder (inside the auth card) for Suspense / loading. */
export function LoginFormSkeleton() {
  return (
    <div className="flex flex-col gap-5">
      <div className="flex flex-col gap-2">
        <Skeleton className="h-5 w-20" />
        <Skeleton className="h-14 w-full rounded-input" />
      </div>
      <div className="flex flex-col gap-2">
        <Skeleton className="h-5 w-24" />
        <Skeleton className="h-14 w-full rounded-input" />
      </div>
      <Skeleton className="h-14 w-full rounded-pill" />
    </div>
  );
}
