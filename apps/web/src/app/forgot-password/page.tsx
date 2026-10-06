import { AuthPage } from "@/components/auth/auth-page";
import { ForgotPasswordForm } from "@/components/auth/forgot-password-form";
import { redirectSignedInToAccount } from "@/lib/auth/redirect-signed-in";
import { pageMetadata } from "@/lib/metadata";
import { t } from "@/i18n/t";
import type { Metadata } from "next";

export const metadata: Metadata = pageMetadata(
  t("auth.forgotPasswordTitle"),
  t("auth.forgotPasswordDescription"),
);

export default async function ForgotPasswordPage() {
  await redirectSignedInToAccount();

  return (
    <AuthPage
      title={t("auth.forgotPasswordTitle")}
      description={t("auth.forgotPasswordDescription")}
    >
      <ForgotPasswordForm />
    </AuthPage>
  );
}
