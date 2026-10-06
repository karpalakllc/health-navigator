"use client";

import Link from "next/link";
import { useState } from "react";
import { AuthFormCard } from "@/components/auth/auth-form-card";
import { PrivacyNote } from "@/components/auth/privacy-note";
import { filterInputClassName } from "@/components/directory/filter-form";
import { Button } from "@/components/ui/button";
import { t } from "@/i18n/t";
import { FormError, FormSuccess } from "@/components/ui/form-message";

export function ForgotPasswordForm() {
  const [email, setEmail] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState(false);
  const [pending, setPending] = useState(false);

  async function handleSubmit(event: React.FormEvent) {
    event.preventDefault();
    setError(null);
    setSuccess(false);
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
    <AuthFormCard>
      {/*
        The status region stays mounted across the swap from form to
        confirmation, so the confirmation is announced (see FormSuccess).
      */}
      <FormSuccess tone="muted">
        {success ? t("auth.forgotPasswordSuccess") : null}
      </FormSuccess>
      {success ? (
        <div className="mt-2">
          <PrivacyNote />
        </div>
      ) : (
        <form onSubmit={handleSubmit} className="grid gap-4">
          <label className="grid gap-1.5 text-sm">
            <span className="font-medium text-foreground">
              {t("auth.email")}
            </span>
            <input
              type="email"
              name="email"
              autoComplete="email"
              required
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              className={filterInputClassName}
            />
          </label>
          {error ? <FormError>{error}</FormError> : null}
          <Button
            type="submit"
            disabled={pending}
            className="min-h-[44px] w-full sm:w-auto"
          >
            {pending
              ? t("auth.forgotPasswordSending")
              : t("auth.forgotPasswordSubmit")}
          </Button>
        </form>
      )}
      <p className="mt-4 text-sm text-muted-foreground">
        <Link
          href="/login"
          className="font-medium text-primary underline-offset-4 hover:underline"
        >
          {t("auth.backToLogin")}
        </Link>
      </p>
    </AuthFormCard>
  );
}
