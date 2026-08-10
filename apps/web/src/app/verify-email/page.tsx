import Link from "next/link";
import { PageShell } from "@/components/ui/page-shell";
import { ResendVerificationForm } from "@/components/auth/resend-verification-form";
import { pageMetadata } from "@/lib/metadata";
import { t } from "@/i18n/t";

export const metadata = pageMetadata(t("auth.verifiedTitle"), undefined, {
  noIndex: true,
});

type VerifyEmailPageProps = {
  searchParams: Promise<{ status?: string }>;
};

/**
 * Landing page for the link in the verification email. The API validates the
 * signature and redirects here with the outcome, so this page only reports it —
 * it never sees the signature itself.
 */
export default async function VerifyEmailPage({ searchParams }: VerifyEmailPageProps) {
  const { status } = await searchParams;

  const copy =
    status === "verified"
      ? { title: t("auth.verifiedTitle"), body: t("auth.verifiedBody"), showResend: false }
      : status === "already"
        ? {
            title: t("auth.verifiedAlreadyTitle"),
            body: t("auth.verifiedAlreadyBody"),
            showResend: false,
          }
        : {
            title: t("auth.verifiedInvalidTitle"),
            body: t("auth.verifiedInvalidBody"),
            showResend: true,
          };

  return (
    <PageShell className="max-w-xl py-16">
      <div className="grid gap-4 rounded-2xl border border-border bg-card p-6">
        <h1 className="text-2xl font-semibold text-foreground">{copy.title}</h1>
        <p className="text-sm text-muted-foreground">{copy.body}</p>

        {copy.showResend ? (
          <ResendVerificationForm />
        ) : (
          <Link
            href="/login"
            className="text-sm font-semibold text-primary underline-offset-4 hover:underline"
          >
            {t("auth.signIn")}
          </Link>
        )}
      </div>
    </PageShell>
  );
}
