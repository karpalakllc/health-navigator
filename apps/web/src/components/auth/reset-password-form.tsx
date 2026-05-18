"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { useState } from "react";
import { AuthFormCard } from "@/components/auth/auth-form-card";
import { PasswordInput } from "@/components/auth/password-input";
import { filterInputClassName } from "@/components/directory/filter-form";
import { Button } from "@/components/ui/button";
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
          payload.message ?? payload.errors?.email?.[0] ?? t("auth.resetPasswordFailed"),
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
    <AuthFormCard>
      <form onSubmit={handleSubmit} className="grid gap-4">
        <label className="grid gap-1.5 text-sm">
          <span className="font-medium text-foreground">{t("auth.email")}</span>
          <input
            type="email"
            name="email"
            autoComplete="email"
            readOnly
            value={email}
            className={filterInputClassName}
          />
        </label>
        <label className="grid gap-1.5 text-sm">
          <span className="font-medium text-foreground">{t("auth.password")}</span>
          <PasswordInput
            id="reset-password"
            name="password"
            autoComplete="new-password"
            required
            value={password}
            onChange={setPassword}
          />
        </label>
        <label className="grid gap-1.5 text-sm">
          <span className="font-medium text-foreground">{t("auth.registerPasswordConfirm")}</span>
          <PasswordInput
            id="reset-password-confirm"
            name="password_confirmation"
            autoComplete="new-password"
            required
            value={passwordConfirmation}
            onChange={setPasswordConfirmation}
          />
        </label>
        {error ? <p className="text-sm text-destructive">{error}</p> : null}
        <Button type="submit" disabled={pending} className="min-h-[44px] w-full sm:w-auto">
          {pending ? t("auth.resetPasswordSaving") : t("auth.resetPasswordSubmit")}
        </Button>
      </form>
      <p className="mt-4 text-sm text-muted-foreground">
        <Link href="/login" className="font-medium text-primary underline-offset-4 hover:underline">
          {t("auth.backToLogin")}
        </Link>
      </p>
    </AuthFormCard>
  );
}
