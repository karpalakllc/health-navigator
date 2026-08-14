import { AuthTrustAside } from "@/components/auth/auth-trust-aside";
import { RegisterForm } from "@/components/auth/register-form";
import { AuthSplitLayout } from "@/components/design/auth-split-layout";
import { DirectoryHero } from "@/components/design/directory-hero";
import { PageShell } from "@/components/ui/page-shell";
import { PageHeroBleed } from "@/components/design/page-hero-bleed";
import { fetchPublicSettings } from "@/lib/api/settings";
import { pageMetadata } from "@/lib/metadata";
import { t } from "@/i18n/t";
import type { Metadata } from "next";

export const metadata: Metadata = pageMetadata(
  t("auth.registerTitle"),
  t("auth.registerDescription"),
);

export default async function RegisterPage() {
  const settings = await fetchPublicSettings();

  return (
    <>
      <PageHeroBleed className="lg:hidden">
        <DirectoryHero
          badge={t("auth.loginAsideTitle")}
          title={t("auth.registerTitle")}
          description={t("auth.registerDescription")}
        />
      </PageHeroBleed>

      <PageShell className="pb-16">
        <AuthSplitLayout aside={<AuthTrustAside />}>
          <div className="hidden lg:block">
            <h1 className="text-3xl font-black tracking-tight text-foreground">
              {t("auth.registerTitle")}
            </h1>
            <p className="mt-2 text-muted-foreground">
              {t("auth.registerDescription")}
            </p>
          </div>
          <RegisterForm registrationsEnabled={settings.registrations_enabled} />
        </AuthSplitLayout>
      </PageShell>
    </>
  );
}
