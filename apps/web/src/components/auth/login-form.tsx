"use client";

import Link from "next/link";
import { useRouter, useSearchParams } from "next/navigation";
import { useState } from "react";
import { AuthStateHeader } from "@/components/auth/auth-page";
import { PasswordField } from "@/components/auth/password-input";
import { ResendVerificationForm } from "@/components/auth/resend-verification-form";
import { TextField } from "@/components/auth/text-field";
import { Button, TextLink } from "@/components/ui/button";
import { FormError } from "@/components/ui/form-message";
import { Notice } from "@/components/ui/notice";
import { safeRedirectTarget } from "@/lib/auth/login-href";
import {
  compactErrors,
  emailError,
  focusField,
  requiredError,
} from "@/lib/form-validation";
import { t } from "@/i18n/t";

const LOGIN_EMAIL_ID = "login-email";
const LOGIN_PASSWORD_ID = "login-password";

export function LoginForm() {
  const router = useRouter();
  const searchParams = useSearchParams();
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [unverified, setUnverified] = useState(false);
  const [pending, setPending] = useState(false);
  const [fieldErrors, setFieldErrors] = useState<
    Partial<Record<"email" | "password", string>>
  >({});
  // Set by the reset-password form once the new password is saved.
  const passwordWasReset = searchParams.get("reset") === "1";

  async function handleSubmit(event: React.FormEvent) {
    event.preventDefault();
    setError(null);

    const local = compactErrors({
      email: emailError(email),
      password: requiredError(password),
    });
    setFieldErrors(local);

    if (local.email || local.password) {
      focusField(local.email ? LOGIN_EMAIL_ID : LOGIN_PASSWORD_ID);
      return;
    }

    setPending(true);

    try {
      const response = await fetch("/api/session/login", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ email, password }),
      });

      const payload = await response.json();

      if (!response.ok) {
        // The account exists and the password was right — it just is not
        // activated yet, so offer the resend rather than a dead end.
        if (payload.code === "auth.email_unverified") {
          setUnverified(true);
          setError(null);
          return;
        }

        setError(
          payload.message ??
            payload.errors?.email?.[0] ??
            t("auth.loginFailed"),
        );
        return;
      }

      const redirect = safeRedirectTarget(searchParams.get("redirect"), "/");
      router.push(redirect);
      router.refresh();
    } catch {
      setError(t("auth.loginFailed"));
    } finally {
      setPending(false);
    }
  }

  if (unverified) {
    return (
      <div className="flex flex-col gap-5">
        <AuthStateHeader icon="mail" title={t("auth.verifyCheckInbox")} />
        <p className="type-body text-ink">{t("auth.verifyUnverified")}</p>
        <ResendVerificationForm defaultEmail={email} />
      </div>
    );
  }

  return (
    <div className="flex flex-col gap-6">
      {passwordWasReset ? (
        <Notice tone="success">{t("auth.resetPasswordDone")}</Notice>
      ) : null}
      <form noValidate onSubmit={handleSubmit} className="flex flex-col gap-5">
        <TextField
          id={LOGIN_EMAIL_ID}
          label={t("auth.email")}
          error={fieldErrors.email}
          type="email"
          name="email"
          autoComplete="email"
          required
          value={email}
          onChange={(e) => setEmail(e.target.value)}
        />
        {/*
          The forgot-password link sits beside the label, not inside it: a link
          inside a <label> makes the label's accessible name include the link
          text, and clicking it is ambiguous between focusing and navigating.
        */}
        <PasswordField
          id={LOGIN_PASSWORD_ID}
          label={t("auth.password")}
          error={fieldErrors.password}
          name="password"
          autoComplete="current-password"
          required
          value={password}
          onChange={setPassword}
          labelAside={
            <TextLink href="/forgot-password" className="type-meta">
              {t("auth.forgotPassword")}
            </TextLink>
          }
        />
        {error ? <FormError>{error}</FormError> : null}
        <Button
          type="submit"
          size="lg"
          fullWidth
          loading={pending}
          disabled={pending}
        >
          {pending ? t("auth.signingIn") : t("auth.signIn")}
        </Button>
      </form>
      <p className="flex flex-wrap items-center gap-x-2 border-t border-line pt-4 type-body text-ink-2">
        <span>{t("auth.noAccount")}</span>
        <TextLink href="/register">{t("auth.register")}</TextLink>
      </p>
      <p className="type-meta text-ink-2">
        {t("auth.termsNotice")}{" "}
        <Link href="/terms" className="link-underline font-semibold text-ink">
          {t("auth.termsLink")}
        </Link>{" "}
        {t("auth.and")}{" "}
        <Link
          href="/disclaimer"
          className="link-underline font-semibold text-ink"
        >
          {t("auth.disclaimerLink")}
        </Link>
        .
      </p>
    </div>
  );
}
