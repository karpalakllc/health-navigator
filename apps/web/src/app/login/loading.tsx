import { LoginFormSkeleton } from "@/components/auth/login-form-skeleton";
import { PageHeader } from "@/components/directory/page-header";
import { PageShell } from "@/components/ui/page-shell";
import { Skeleton } from "@/components/ui/skeleton";
import { t } from "@/i18n/t";

export default function Loading() {
  return (
    <PageShell className="pb-20">
      <div className="mx-auto grid max-w-5xl gap-10 lg:grid-cols-5 lg:items-start lg:gap-14">
        <div className="hidden lg:col-span-2 lg:block">
          <Skeleton className="h-48 w-full rounded-2xl" />
        </div>
        <div className="space-y-6 lg:col-span-3">
          <PageHeader
            title={t("auth.loginTitle")}
            description={t("auth.loginDescription")}
          />
          <LoginFormSkeleton />
        </div>
      </div>
    </PageShell>
  );
}
