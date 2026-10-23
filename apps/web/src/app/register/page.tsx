import { AuthPage } from "@/components/auth/auth-page";
import { RegisterForm } from "@/components/auth/register-form";
import { fetchPublicSettings } from "@/lib/api/settings";
import { safeRedirectTarget } from "@/lib/auth/login-href";
import { redirectSignedInToAccount } from "@/lib/auth/redirect-signed-in";
import { pageMetadata } from "@/lib/metadata";
import { t } from "@/i18n/t";
import type { Metadata } from "next";

export const metadata: Metadata = pageMetadata(
  t("auth.registerTitle"),
  t("auth.registerDescription"),
);

type RegisterPageProps = {
  searchParams: Promise<{ redirect?: string | string[] }>;
};

export default async function RegisterPage({
  searchParams,
}: RegisterPageProps) {
  await redirectSignedInToAccount();
  const settings = await fetchPublicSettings();
  // Carried from „Најава“ (e.g. back to a review form), to the sign-in link
  // shown once the account is created. Same-origin paths only.
  const { redirect } = await searchParams;
  const redirectTo =
    typeof redirect === "string"
      ? safeRedirectTarget(redirect, "") || null
      : null;

  return (
    <AuthPage
      title={t("auth.registerTitle")}
      description={t("auth.registerDescription")}
    >
      <RegisterForm
        registrationsEnabled={settings.registrations_enabled}
        redirectTo={redirectTo}
      />
    </AuthPage>
  );
}
