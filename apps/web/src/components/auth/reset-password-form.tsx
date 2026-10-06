"use client";

import { useRouter } from "next/navigation";
import { useState } from "react";
import { PasswordField } from "@/components/auth/password-input";
import { TextField } from "@/components/auth/text-field";
import { Button, TextLink } from "@/components/ui/button";
import { FormError } from "@/components/ui/form-message";
import { Icon } from "@/components/ui/icons";
import {
  compactErrors,
  focusField,
  mapApiFieldErrors,
  passwordConfirmationError,
  requiredError,
} from "@/lib/form-validation";
import { t } from "@/i18n/t";

type ResetField = "password" | "password_confirmation";
const RESET_FIELDS: readonly ResetField[] = [
  "password",
  "password_confirmation",
];

type ResetPasswordFormProps = {
  email: string;
  token: string;
};

export function ResetPasswordForm({ email, token }: ResetPasswordFormProps) {
  const router = useRouter();
  const [password, setPassword] = useState("");
  const [passwordConfirmation, setPasswordConfirmation] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [pending, setPending] = useState(false);
  const [fieldErrors, setFieldErrors] = useState<
    Partial<Record<ResetField, string>>
  >({});

  function reject(errors: Partial<Record<ResetField, string>>) {
    setFieldErrors(errors);
    focusField(
      errors.password ? "reset-password" : "reset-password-confirm",
    );
  }

  async function handleSubmit(event: React.FormEvent) {
    event.preventDefault();
    setError(null);

    const local = compactErrors<ResetField>({
      password: requiredError(password),
      password_confirmation: passwordConfirmationError(
        password,
        passwordConfirmation,
      ),
    });

    if (local.password || local.password_confirmation) {
      reject(local);
      return;
    }

    setFieldErrors({});
    setPending(true);

    try {
      const response = await fetch("/api/session/reset-password", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          email,
          token,
          password,
          password_confirmation: passwordConfirmation,
        }),
      });

      const payload = await response.json();

      if (!response.ok) {
        // Password problems go under their field; anything else (a stale
        // token, the e-mail) stays the form-level message.
        const mapped = mapApiFieldErrors(payload.errors, RESET_FIELDS, {
          passwordField: "password",
          confirmationField: "password_confirmation",
        });

        if (mapped.password || mapped.password_confirmation) {
          reject(mapped);
          return;
        }

        setError(
          payload.message ??
            payload.errors?.email?.[0] ??
            t("auth.resetPasswordFailed"),
        );
        return;
      }

      router.push("/login?reset=1");
      router.refresh();
    } catch {
      setError(t("auth.resetPasswordFailed"));
    } finally {
      setPending(false);
    }
  }

  return (
    <div className="flex flex-col gap-6">
      <form noValidate onSubmit={handleSubmit} className="flex flex-col gap-5">
        <TextField
          label={t("auth.email")}
          type="email"
          name="email"
          autoComplete="email"
          readOnly
          value={email}
        />
        <PasswordField
          id="reset-password"
          label={t("auth.password")}
          hint={t("auth.passwordRules")}
          error={fieldErrors.password}
          name="password"
          autoComplete="new-password"
          required
          value={password}
          onChange={setPassword}
        />
        <PasswordField
          id="reset-password-confirm"
          label={t("auth.registerPasswordConfirm")}
          error={fieldErrors.password_confirmation}
          name="password_confirmation"
          autoComplete="new-password"
          required
          value={passwordConfirmation}
          onChange={setPasswordConfirmation}
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
            ? t("auth.resetPasswordSaving")
            : t("auth.resetPasswordSubmit")}
        </Button>
      </form>
      <p className="border-t border-line pt-4">
        <TextLink href="/login">
          <Icon name="arrow-left" size={20} />
          {t("auth.backToLogin")}
        </TextLink>
      </p>
    </div>
  );
}
