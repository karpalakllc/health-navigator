import { Skeleton } from "@/components/ui/skeleton";
import { t } from "@/i18n/t";

/**
 * Fallback for any route without its own loading.tsx: a neutral page shape
 * (title, a line, three cards) rather than one section's layout. The status
 * text is for screen readers; the blocks are aria-hidden.
 */
export default function Loading() {
  return (
    <div className="mx-auto flex w-full max-w-[1240px] flex-col gap-6 px-5 pb-14 pt-6 lg:gap-10 lg:px-6 lg:pt-10">
      <p role="status" className="sr-only">
        {t("ui.loading")}
      </p>
      <div className="flex flex-col gap-3">
        <Skeleton className="h-5 w-28" />
        <Skeleton className="h-9 w-72 max-w-[85%] lg:h-11" />
        <Skeleton className="h-5 w-full max-w-xl" />
      </div>
      <div className="grid gap-4 lg:grid-cols-3 lg:gap-6">
        {[0, 1, 2].map((i) => (
          <div key={i} className="card flex flex-col gap-4 p-5">
            <div className="flex items-center gap-3">
              <Skeleton className="size-14 rounded-full" />
              <div className="flex flex-1 flex-col gap-2">
                <Skeleton className="h-5 w-3/4" />
                <Skeleton className="h-4 w-1/2" />
              </div>
            </div>
            <Skeleton className="h-4 w-full" />
            <Skeleton className="h-4 w-5/6" />
            <Skeleton className="h-12 w-full rounded-pill" />
          </div>
        ))}
      </div>
    </div>
  );
}
