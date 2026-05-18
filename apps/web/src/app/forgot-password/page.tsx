import { AuthTrustAside } from "@/components/auth/auth-trust-aside";
import { ForgotPasswordForm } from "@/components/auth/forgot-password-form";
import { AuthSplitLayout } from "@/components/design/auth-split-layout";
import { DirectoryHero } from "@/components/design/directory-hero";
import { PageShell } from "@/components/ui/page-shell";
import { PageHeroBleed } from "@/components/design/page-hero-bleed";
import { pageMetadata } from "@/lib/metadata";
import { t } from "@/i18n/t";
import type { Metadata } from "next";

export const metadata: Metadata = pageMetadata(
  t("auth.forgotPasswordTitle"),
  t("auth.forgotPasswordDescription"),
);

export default function ForgotPasswordPage() {
  return (
    <>
      <PageHeroBleed className="lg:hidden">
        <DirectoryHero
          badge={t("auth.loginAsideTitle")}
          title={t("auth.forgotPasswordTitle")}
          description={t("auth.forgotPasswordDescription")}
        />
      </PageHeroBleed>

      <PageShell className="pb-16">
        <AuthSplitLayout aside={<AuthTrustAside />}>
          <div className="hidden lg:block">
            <h1 className="text-3xl font-black tracking-tight text-foreground">
              {t("auth.forgotPasswordTitle")}
            </h1>
            <p className="mt-2 text-muted-foreground">{t("auth.forgotPasswordDescription")}</p>
          </div>
          <ForgotPasswordForm />
        </AuthSplitLayout>
      </PageShell>
    </>
  );
}
