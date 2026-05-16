import { RegisterForm } from "@/components/auth/register-form";
import { PageHeader } from "@/components/directory/page-header";
import { PageShell } from "@/components/ui/page-shell";
import { fetchPublicSettings } from "@/lib/api/settings";
import { t } from "@/i18n/t";

export default async function RegisterPage() {
  const settings = await fetchPublicSettings();

  return (
    <PageShell className="pb-20">
      <div className="mx-auto max-w-lg space-y-6">
        <PageHeader
          title={t("auth.registerTitle")}
          description={t("auth.registerDescription")}
        />
        <RegisterForm registrationsEnabled={settings.registrations_enabled} />
      </div>
    </PageShell>
  );
}
