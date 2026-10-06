import { Skeleton } from "@/components/ui/skeleton";

/** Loading placeholder shaped like DirectoryListView (strip, title, rail, cards). */
export function DirectoryListPageSkeleton() {
  return (
    <div
      aria-busy="true"
      className="mx-auto flex w-full max-w-[1240px] flex-col px-5 pb-10 lg:px-6"
    >
      <Skeleton className="mt-2 h-14 w-full rounded-pill lg:hidden" />
      <Skeleton className="mt-8 hidden h-5 w-40 lg:block" />
      <Skeleton className="mt-5 h-8 w-48 lg:mt-6 lg:h-11 lg:w-64" />
      <Skeleton className="mt-2 h-5 w-28" />
      <div className="mt-5 flex gap-2 overflow-hidden">
        {Array.from({ length: 4 }).map((_, index) => (
          <Skeleton key={index} className="h-11 w-32 shrink-0 rounded-pill" />
        ))}
      </div>
      <div className="mt-5 grid gap-5 lg:mt-8 lg:grid-cols-12">
        <Skeleton className="hidden h-96 rounded-card lg:col-span-4 lg:block" />
        <div className="grid gap-3 md:grid-cols-2 lg:col-span-8 lg:gap-5">
          {Array.from({ length: 4 }).map((_, index) => (
            <Skeleton key={index} className="h-60 rounded-card" />
          ))}
        </div>
      </div>
    </div>
  );
}
