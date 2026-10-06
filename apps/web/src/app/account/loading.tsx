import { AccountPage } from "@/components/account/account-layout";
import { Skeleton } from "@/components/ui/skeleton";
import { t } from "@/i18n/t";

/** The account pages' shape: header, section nav, two cards. */
export default function Loading() {
  return (
    <AccountPage>
      <p role="status" className="sr-only">
        {t("ui.loading")}
      </p>
      <div className="flex flex-col gap-3">
        <Skeleton className="h-5 w-32" />
        <Skeleton className="h-9 w-48 lg:h-11" />
        <Skeleton className="h-5 w-full max-w-lg" />
      </div>
      <div className="flex flex-col gap-6 lg:flex-row lg:items-start lg:gap-10">
        <div className="flex gap-2 lg:w-64 lg:shrink-0 lg:flex-col">
          <Skeleton className="h-12 w-32 rounded-pill lg:h-14 lg:w-full" />
          <Skeleton className="h-12 w-36 rounded-pill lg:h-14 lg:w-full" />
          <Skeleton className="h-12 w-28 rounded-pill lg:h-14 lg:w-full" />
        </div>
        <div className="flex min-w-0 flex-1 flex-col gap-6">
          <div className="card flex flex-col gap-4 p-6">
            <Skeleton className="h-7 w-40" />
            <Skeleton className="h-5 w-full max-w-md" />
            <Skeleton className="h-5 w-2/3" />
            <Skeleton className="h-14 w-full rounded-input" />
          </div>
          <div className="card flex items-center gap-5 p-6">
            <Skeleton className="size-20 rounded-full" />
            <div className="flex flex-1 flex-col gap-2">
              <Skeleton className="h-5 w-3/4" />
              <Skeleton className="h-2 w-full rounded-pill" />
            </div>
          </div>
        </div>
      </div>
    </AccountPage>
  );
}
