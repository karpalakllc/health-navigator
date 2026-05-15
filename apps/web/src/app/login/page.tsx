import { Suspense } from "react";
import { LoginForm } from "@/components/auth/login-form";
import { LoginFormSkeleton } from "@/components/auth/login-form-skeleton";
import { PageHeader } from "@/components/directory/page-header";
import { PageShell } from "@/components/ui/page-shell";
import { t } from "@/i18n/t";

export default function LoginPage() {
  return (
    <PageShell className="pb-20">
      <div className="mx-auto grid max-w-5xl gap-10 lg:grid-cols-5 lg:items-start lg:gap-14">
        <div className="hidden flex-col gap-4 rounded-2xl border border-border bg-gradient-to-br from-primary/5 via-card to-accent/5 p-8 lg:col-span-2 lg:flex">
          <p className="text-sm font-semibold text-foreground">{t("auth.loginAsideTitle")}</p>
          <ul className="list-disc space-y-2 pl-4 text-sm text-muted-foreground">
            <li>{t("home.trustModerated")}</li>
            <li>{t("home.trustInformational")}</li>
          </ul>
          <p className="text-xs text-muted-foreground">{t("home.trustEmergency")}</p>
        </div>
        <div className="space-y-6 lg:col-span-3">
          <PageHeader title={t("auth.loginTitle")} description={t("auth.loginDescription")} />
          <Suspense fallback={<LoginFormSkeleton />}>
            <LoginForm />
          </Suspense>
        </div>
      </div>
    </PageShell>
  );
}
