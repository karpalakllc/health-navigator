import { AuthPage } from "@/components/auth/auth-page";
import { RegisterForm } from "@/components/auth/register-form";
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
    <AuthPage
      title={t("auth.registerTitle")}
      description={t("auth.registerDescription")}
    >
      <RegisterForm registrationsEnabled={settings.registrations_enabled} />
    </AuthPage>
  );
}
