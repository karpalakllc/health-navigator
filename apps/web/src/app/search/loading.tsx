import { Skeleton } from "@/components/ui/skeleton";
import { t } from "@/i18n/t";

/** Mirrors the /search layout: apricot band with the form, then result cards. */
export default function Loading() {
  return (
    <div
      className="mx-auto w-full max-w-[1240px] pb-14 lg:px-6 lg:pb-20"
      aria-busy="true"
    >
      <p className="sr-only" role="status">
        {t("ui.loading")}
      </p>
      <div className="mx-3 mt-1 rounded-sheet bg-apricot px-5 pb-5 pt-7 lg:mx-0 lg:mt-6 lg:px-14 lg:pb-10 lg:pt-12">
        <Skeleton className="h-5 w-28 bg-white/70" />
        <Skeleton className="mt-3 h-9 w-3/4 bg-white/70 lg:h-12" />
        <Skeleton className="mt-6 h-40 w-full rounded-card bg-white lg:h-28" />
      </div>
      <div className="mt-8 flex flex-col gap-4 px-5 lg:mt-14 lg:px-0">
        <Skeleton className="h-7 w-40" />
        <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
          {[0, 1, 2].map((i) => (
            <Skeleton key={i} className="h-56 rounded-card" />
          ))}
        </div>
      </div>
    </div>
  );
}
