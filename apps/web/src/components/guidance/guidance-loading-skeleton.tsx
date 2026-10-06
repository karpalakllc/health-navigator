import { guidancePageClass } from "@/components/guidance/guidance-layout";
import { Skeleton } from "@/components/ui/skeleton";

/** Mirrors the intro: apricot hero, the red-flag card, the note, the action. */
export function GuidancePageSkeleton() {
  return (
    <div className={`${guidancePageClass} gap-6 lg:gap-8`}>
      <Skeleton className="h-56 w-full rounded-sheet lg:h-52" />
      <Skeleton className="h-64 w-full rounded-card" />
      <Skeleton className="h-28 w-full rounded-card" />
      <Skeleton className="h-14 w-full rounded-pill lg:w-48" />
    </div>
  );
}
