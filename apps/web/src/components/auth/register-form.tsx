"use client";

import Link from "next/link";
import { useId, useState } from "react";
import { AuthFormCard } from "@/components/auth/auth-form-card";
import { PasswordInput } from "@/components/auth/password-input";
import { PrivacyNote } from "@/components/auth/privacy-note";
import { filterInputClassName } from "@/components/directory/filter-form";
import { Button } from "@/components/ui/button";
import {
  DISPLAY_NAME_MAX_LENGTH,
  suggestDisplayName,
} from "@/lib/display-name";
import { t } from "@/i18n/t";
import { FormError } from "@/components/ui/form-message";

type RegisterFormProps = {
  registrationsEnabled: boolean;
};

export function RegisterForm({ registrationsEnabled }: RegisterFormProps) {
  const [name, setName] = useState("");
  // Follows the name ("Марија К.") until the person types their own.
  const [customDisplayName, setCustomDisplayName] = useState<string | null>(
    null,
  );
  const displayName = customDisplayName ?? suggestDisplayName(name);
  const nameId = useId();
  const nameHelpId = useId();
  const displayNameId = useId();
  const displayNameHelpId = useId();
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [passwordConfirmation, setPasswordConfirmation] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [pending, setPending] = useState(false);
  const [submitted, setSubmitted] = useState(false);

  if (!registrationsEnabled) {
    return (
      <p className="rounded-2xl border border-border bg-card p-6 text-sm text-muted-foreground">
        {t("auth.registerDisabled")}
      </p>
    );
  }

  async function handleSubmit(event: React.FormEvent) {
    event.preventDefault();
    setError(null);
    setPending(true);

    try {
      const response = await fetch("/api/session/register", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          name,
          display_name: displayName,
          email,
          password,
          password_confirmation: passwordConfirmation,
        }),
      });

      const payload = await response.json();

      if (!response.ok) {
        setError(
          payload.message ??
            payload.errors?.display_name?.[0] ??
            payload.errors?.email?.[0] ??
            payload.errors?.password?.[0] ??
            t("auth.registerFailed"),
        );
        return;
      }

      // No session yet: the account is not usable until the address is verified,
      // and the API deliberately does not say whether it was already registered.
      setSubmitted(true);
    } catch {
      setError(t("auth.registerFailed"));
    } finally {
      setPending(false);
    }
  }

  if (submitted) {
    return (
      <AuthFormCard>
        <div className="grid gap-3">
          <h2 className="text-lg font-semibold text-foreground">
            {t("auth.verifyCheckInbox")}
          </h2>
          <p role="status" className="text-sm text-muted-foreground">
            {t("auth.verifyCheckInboxBody")}
          </p>
          <PrivacyNote />
          <Link
            href="/login"
            className="text-sm font-semibold text-primary underline-offset-4 hover:underline"
          >
            {t("auth.signIn")}
          </Link>
        </div>
      </AuthFormCard>
    );
  }

  return (
    <AuthFormCard>
      <form onSubmit={handleSubmit} className="grid gap-4">
        {/* Help text sits outside the <label> so it describes the field
            (aria-describedby) instead of becoming part of its name. */}
        <div className="grid gap-1.5 text-sm">
          <label htmlFor={nameId} className="font-medium text-foreground">
            {t("auth.registerName")}
          </label>
          <input
            id={nameId}
            type="text"
            name="name"
            required
            autoComplete="name"
            aria-describedby={nameHelpId}
            value={name}
            onChange={(e) => setName(e.target.value)}
            className={filterInputClassName}
          />
          <p id={nameHelpId} className="text-xs text-muted-foreground">
            {t("auth.registerNameHelp")}
          </p>
        </div>
        <div className="grid gap-1.5 text-sm">
          <label
            htmlFor={displayNameId}
            className="font-medium text-foreground"
          >
            {t("auth.registerDisplayName")}
          </label>
          <input
            id={displayNameId}
            type="text"
            name="display_name"
            required
            maxLength={DISPLAY_NAME_MAX_LENGTH}
            autoComplete="nickname"
            aria-describedby={displayNameHelpId}
            value={displayName}
            onChange={(e) => setCustomDisplayName(e.target.value)}
            className={filterInputClassName}
          />
          <p id={displayNameHelpId} className="text-xs text-muted-foreground">
            {t("auth.registerDisplayNameHelp")}
          </p>
        </div>
        <label className="grid gap-1.5 text-sm">
          <span className="font-medium text-foreground">{t("auth.email")}</span>
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
        <label className="grid gap-1.5 text-sm">
          <span className="font-medium text-foreground">
            {t("auth.password")}
          </span>
          <PasswordInput
            id="register-password"
            name="password"
            autoComplete="new-password"
            required
            value={password}
            onChange={setPassword}
          />
        </label>
        <label className="grid gap-1.5 text-sm">
          <span className="font-medium text-foreground">
            {t("auth.registerPasswordConfirm")}
          </span>
          <PasswordInput
            id="register-password-confirm"
            name="password_confirmation"
            autoComplete="new-password"
            required
            value={passwordConfirmation}
            onChange={setPasswordConfirmation}
          />
        </label>
        {error ? <FormError>{error}</FormError> : null}
        <Button
          type="submit"
          disabled={pending}
          className="min-h-[44px] w-full sm:w-auto"
        >
          {pending ? t("auth.registering") : t("auth.register")}
        </Button>
        <p className="text-sm text-muted-foreground">
          {t("auth.haveAccount")}{" "}
          <Link
            href="/login"
            className="font-medium text-primary underline-offset-4 hover:underline"
          >
            {t("auth.signIn")}
          </Link>
        </p>
      </form>
    </AuthFormCard>
  );
}
