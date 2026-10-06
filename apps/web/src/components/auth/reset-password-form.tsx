"use client";

import { useRouter } from "next/navigation";
import { useState } from "react";
import { PasswordField } from "@/components/auth/password-input";
import { TextField } from "@/components/auth/text-field";
import { Button, TextLink } from "@/components/ui/button";
import { FormError } from "@/components/ui/form-message";
import { Icon } from "@/components/ui/icons";
import { t } from "@/i18n/t";

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

  async function handleSubmit(event: React.FormEvent) {
    event.preventDefault();
    setError(null);
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
      <form onSubmit={handleSubmit} className="flex flex-col gap-5">
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
          name="password"
          autoComplete="new-password"
          required
          value={password}
          onChange={setPassword}
        />
        <PasswordField
          id="reset-password-confirm"
          label={t("auth.registerPasswordConfirm")}
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
