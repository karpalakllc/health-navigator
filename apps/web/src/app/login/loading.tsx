import { AuthPage } from "@/components/auth/auth-page";
import { LoginFormSkeleton } from "@/components/auth/login-form-skeleton";
import { t } from "@/i18n/t";

export default function Loading() {
  return (
    <AuthPage title={t("auth.loginTitle")}>
      <LoginFormSkeleton />
    </AuthPage>
  );
}
