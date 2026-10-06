import { cookies } from "next/headers";
import { notFound } from "next/navigation";
import { AuthPage } from "@/components/auth/auth-page";
import { ResetPasswordForm } from "@/components/auth/reset-password-form";
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
    <AuthPage
      title={t("auth.resetPasswordTitle")}
      description={t("auth.resetPasswordDescription")}
    >
      <ResetPasswordForm email={credentials.email} token={credentials.token} />
    </AuthPage>
  );
}
