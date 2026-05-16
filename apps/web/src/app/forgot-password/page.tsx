import { ForgotPasswordForm } from "@/components/auth/forgot-password-form";
import { PageHeader } from "@/components/directory/page-header";
import { PageShell } from "@/components/ui/page-shell";
import { pageMetadata } from "@/lib/metadata";
import { t } from "@/i18n/t";
import type { Metadata } from "next";

export const metadata: Metadata = pageMetadata(
  t("auth.forgotPasswordTitle"),
  t("auth.forgotPasswordDescription"),
);

export default function ForgotPasswordPage() {
  return (
    <PageShell className="pb-20">
      <div className="mx-auto max-w-lg space-y-6">
        <PageHeader
          title={t("auth.forgotPasswordTitle")}
          description={t("auth.forgotPasswordDescription")}
        />
        <ForgotPasswordForm />
      </div>
    </PageShell>
  );
}
