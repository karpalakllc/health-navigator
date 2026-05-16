import { notFound } from "next/navigation";
import { ResetPasswordForm } from "@/components/auth/reset-password-form";
import { PageHeader } from "@/components/directory/page-header";
import { PageShell } from "@/components/ui/page-shell";
import { pageMetadata } from "@/lib/metadata";
import { t } from "@/i18n/t";
import type { Metadata } from "next";

export const metadata: Metadata = pageMetadata(
  t("auth.resetPasswordTitle"),
  t("auth.resetPasswordDescription"),
);

type ResetPasswordPageProps = {
  searchParams: Promise<{ token?: string; email?: string }>;
};

export default async function ResetPasswordPage({ searchParams }: ResetPasswordPageProps) {
  const params = await searchParams;
  const token = params.token?.trim();
  const email = params.email?.trim();

  if (!token || !email) {
    notFound();
  }

  return (
    <PageShell className="pb-20">
      <div className="mx-auto max-w-lg space-y-6">
        <PageHeader
          title={t("auth.resetPasswordTitle")}
          description={t("auth.resetPasswordDescription")}
        />
        <ResetPasswordForm email={email} token={token} />
      </div>
    </PageShell>
  );
}
