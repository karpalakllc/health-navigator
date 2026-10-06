import { AuthPage } from "@/components/auth/auth-page";
import { ResendVerificationForm } from "@/components/auth/resend-verification-form";
import { Button } from "@/components/ui/button";
import { Notice } from "@/components/ui/notice";
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
export default async function VerifyEmailPage({
  searchParams,
}: VerifyEmailPageProps) {
  const { status } = await searchParams;

  const copy =
    status === "verified"
      ? {
          title: t("auth.verifiedTitle"),
          body: t("auth.verifiedBody"),
          ok: true,
          showResend: false,
        }
      : status === "verified_set_password"
        ? {
            title: t("auth.verifiedSetPasswordTitle"),
            body: t("auth.verifiedSetPasswordBody"),
            ok: true,
            showResend: false,
          }
        : status === "already"
          ? {
              title: t("auth.verifiedAlreadyTitle"),
              body: t("auth.verifiedAlreadyBody"),
              ok: true,
              showResend: false,
            }
          : {
              title: t("auth.verifiedInvalidTitle"),
              body: t("auth.verifiedInvalidBody"),
              ok: false,
              showResend: true,
            };

  return (
    <AuthPage title={copy.title}>
      <div className="flex flex-col gap-6">
        {copy.ok ? (
          <Notice tone="success">{copy.body}</Notice>
        ) : (
          <p className="type-body text-ink">{copy.body}</p>
        )}

        {copy.showResend ? (
          <ResendVerificationForm />
        ) : status === "verified_set_password" ? (
          // No password to sign in with yet; this is where a lost reset mail
          // is re-requested.
          <Button href="/forgot-password" size="lg" fullWidth>
            {t("auth.verifiedSetPasswordLink")}
          </Button>
        ) : (
          <Button href="/login" size="lg" fullWidth>
            {t("auth.signIn")}
          </Button>
        )}
      </div>
    </AuthPage>
  );
}
