import { Suspense } from "react";
import { AuthTrustAside } from "@/components/auth/auth-trust-aside";
import { LoginForm } from "@/components/auth/login-form";
import { LoginFormSkeleton } from "@/components/auth/login-form-skeleton";
import { AuthSplitLayout } from "@/components/design/auth-split-layout";
import { DirectoryHero } from "@/components/design/directory-hero";
import { PageHeroBleed } from "@/components/design/page-hero-bleed";
import { PageShell } from "@/components/ui/page-shell";
import { pageMetadata } from "@/lib/metadata";
import { t } from "@/i18n/t";
import type { Metadata } from "next";

export const metadata: Metadata = pageMetadata(
  t("auth.loginTitle"),
  t("auth.loginDescription"),
);

export default function LoginPage() {
  return (
    <>
      <PageHeroBleed className="lg:hidden">
        <DirectoryHero
          badge={t("auth.loginAsideTitle")}
          title={t("auth.loginTitle")}
          description={t("auth.loginDescription")}
        />
      </PageHeroBleed>
      <PageShell className="pb-16">
        <AuthSplitLayout aside={<AuthTrustAside />}>
          <div className="hidden lg:block">
            <h1 className="text-3xl font-black tracking-tight text-foreground">
              {t("auth.loginTitle")}
            </h1>
            <p className="mt-2 text-muted-foreground">
              {t("auth.loginDescription")}
            </p>
          </div>
          <Suspense fallback={<LoginFormSkeleton />}>
            <LoginForm />
          </Suspense>
        </AuthSplitLayout>
      </PageShell>
    </>
  );
}
