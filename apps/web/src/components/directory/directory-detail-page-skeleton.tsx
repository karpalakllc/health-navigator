import { Skeleton } from "@/components/ui/skeleton";

/** Loading placeholder shaped like a profile (header, contact, sections). */
export function DirectoryDetailPageSkeleton() {
  return (
    <div
      aria-busy="true"
      className="mx-auto w-full max-w-[1240px] px-5 pb-10 lg:px-6"
    >
      <Skeleton className="mt-2 h-11 w-32 rounded-pill lg:hidden" />
      <Skeleton className="mt-8 hidden h-5 w-64 lg:block" />
      <div className="mt-3 grid gap-3 lg:mt-6 lg:grid-cols-12 lg:gap-6">
        <div className="flex flex-col gap-3 lg:col-span-8 lg:gap-6">
          <Skeleton className="h-72 w-full rounded-sheet lg:h-56" />
          <Skeleton className="h-64 w-full rounded-card lg:hidden" />
          <Skeleton className="h-40 w-full rounded-card" />
          <Skeleton className="h-56 w-full rounded-card" />
        </div>
        <Skeleton className="hidden h-[32rem] rounded-card lg:col-span-4 lg:block" />
      </div>
    </div>
  );
}
