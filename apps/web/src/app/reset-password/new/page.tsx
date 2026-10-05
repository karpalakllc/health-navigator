import { cookies } from "next/headers";
import { notFound } from "next/navigation";
import { AuthTrustAside } from "@/components/auth/auth-trust-aside";
import { ResetPasswordForm } from "@/components/auth/reset-password-form";
import { AuthSplitLayout } from "@/components/design/auth-split-layout";
import { DirectoryHero } from "@/components/design/directory-hero";
import { PageShell } from "@/components/ui/page-shell";
import { PageHeroBleed } from "@/components/design/page-hero-bleed";
import { decodeResetCookie, RESET_COOKIE } from "@/lib/auth/reset-token";
import { pageMetadata } from "@/lib/metadata";
import { t } from "@/i18n/t";
import type { Metadata } from "next";

export const metadata: Metadata = pageMetadata(
  t("auth.resetPasswordTitle"),
  t("auth.resetPasswordDescription"),
);

/**
 * The reset form. Token and address arrive in a cookie set by
 * ../route.ts, never in this page's URL — see lib/auth/reset-token.ts.
 */
export default async function ResetPasswordPage() {
  const store = await cookies();
  const credentials = decodeResetCookie(store.get(RESET_COOKIE)?.value);

  if (!credentials) {
    notFound();
  }

  return (
    <>
      <PageHeroBleed className="lg:hidden">
        <DirectoryHero
          badge={t("auth.loginAsideTitle")}
          title={t("auth.resetPasswordTitle")}
          description={t("auth.resetPasswordDescription")}
        />
      </PageHeroBleed>

      <PageShell className="pb-16">
        <AuthSplitLayout aside={<AuthTrustAside />}>
          <div className="hidden lg:block">
            <h1 className="text-3xl font-black tracking-tight text-foreground">
              {t("auth.resetPasswordTitle")}
            </h1>
            <p className="mt-2 text-muted-foreground">
              {t("auth.resetPasswordDescription")}
            </p>
          </div>
          <ResetPasswordForm
            email={credentials.email}
            token={credentials.token}
          />
        </AuthSplitLayout>
      </PageShell>
    </>
  );
}
