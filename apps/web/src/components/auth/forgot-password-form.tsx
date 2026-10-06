"use client";

import { useState } from "react";
import { PrivacyNote } from "@/components/auth/privacy-note";
import { TextField } from "@/components/auth/text-field";
import { Button, TextLink } from "@/components/ui/button";
import { FormError, FormSuccess } from "@/components/ui/form-message";
import { Icon } from "@/components/ui/icons";
import { emailError, focusField } from "@/lib/form-validation";
import { t } from "@/i18n/t";

const FORGOT_EMAIL_ID = "forgot-email";

export function ForgotPasswordForm() {
  const [email, setEmail] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState(false);
  const [pending, setPending] = useState(false);
  const [emailProblem, setEmailProblem] = useState<string | null>(null);

  async function handleSubmit(event: React.FormEvent) {
    event.preventDefault();
    setError(null);
    setSuccess(false);

    const problem = emailError(email);
    setEmailProblem(problem);

    if (problem) {
      focusField(FORGOT_EMAIL_ID);
      return;
    }

    setPending(true);

    try {
      const response = await fetch("/api/session/forgot-password", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ email }),
      });

      const payload = await response.json();

      if (!response.ok) {
        setError(
          payload.message ??
            payload.errors?.email?.[0] ??
            t("auth.forgotPasswordFailed"),
        );
        return;
      }

      setSuccess(true);
    } catch {
      setError(t("auth.forgotPasswordFailed"));
    } finally {
      setPending(false);
    }
  }

  return (
    <div className="flex flex-col gap-6">
      {/*
        The status region stays mounted across the swap from form to
        confirmation, so the confirmation is announced (see FormSuccess). The
        icon sits outside it so the region holds the message alone.
      */}
      <div className={success ? "flex items-start gap-3" : "contents"}>
        {success ? (
          <span className="inline-flex size-12 shrink-0 items-center justify-center rounded-full bg-apricot text-ink">
            <Icon name="mail" size={24} />
          </span>
        ) : null}
        <FormSuccess tone="muted">
          {success ? t("auth.forgotPasswordSuccess") : null}
        </FormSuccess>
      </div>
      {success ? (
        <PrivacyNote />
      ) : (
        <form
          noValidate
          onSubmit={handleSubmit}
          className="flex flex-col gap-5"
        >
          <TextField
            id={FORGOT_EMAIL_ID}
            label={t("auth.email")}
            error={emailProblem}
            type="email"
            name="email"
            autoComplete="email"
            required
            value={email}
            onChange={(e) => setEmail(e.target.value)}
          />
          {error ? <FormError>{error}</FormError> : null}
          <Button
            type="submit"
            size="lg"
            fullWidth
            loading={pending}
            disabled={pending}
          >
            {pending
              ? t("auth.forgotPasswordSending")
              : t("auth.forgotPasswordSubmit")}
          </Button>
        </form>
      )}
      <p className="border-t border-line pt-4">
        <TextLink href="/login">
          <Icon name="arrow-left" size={20} />
          {t("auth.backToLogin")}
        </TextLink>
      </p>
    </div>
  );
}
