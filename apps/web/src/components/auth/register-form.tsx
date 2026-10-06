"use client";

import Link from "next/link";
import { useEffect, useId, useRef, useState } from "react";
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

type Field =
  "name" | "display_name" | "email" | "password" | "password_confirmation";

const FIELDS: Field[] = [
  "name",
  "display_name",
  "email",
  "password",
  "password_confirmation",
];

/** The first message per field from a Laravel 422 `errors` bag. */
function fieldErrorsFrom(errors: unknown): Partial<Record<Field, string>> {
  const out: Partial<Record<Field, string>> = {};

  if (!errors || typeof errors !== "object") {
    return out;
  }

  for (const field of FIELDS) {
    const messages = (errors as Record<string, unknown>)[field];

    if (Array.isArray(messages) && typeof messages[0] === "string") {
      out[field] = messages[0];
    }
  }

  return out;
}

function errorId(field: Field): string {
  return `register-${field}-error`;
}

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
  const [fieldErrors, setFieldErrors] = useState<
    Partial<Record<Field, string>>
  >({});
  const [pending, setPending] = useState(false);
  const [submitted, setSubmitted] = useState(false);
  const successHeadingRef = useRef<HTMLHeadingElement>(null);

  // The success card replaces a taller form, so without this the viewport is
  // left below it (showing the footer) and focus is on a removed button.
  useEffect(() => {
    if (!submitted) {
      return;
    }

    const heading = successHeadingRef.current;
    heading?.scrollIntoView?.({ block: "center" });
    heading?.focus({ preventScroll: true });
  }, [submitted]);

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
    setFieldErrors({});
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
        // Each field shows its own message; the alert keeps the summary
        // (Laravel's "… (и уште N грешки)" names only the first one).
        setFieldErrors(fieldErrorsFrom(payload.errors));
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
          <h2
            ref={successHeadingRef}
            tabIndex={-1}
            className="scroll-mt-24 text-lg font-semibold text-foreground focus:outline-none"
          >
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

  function invalidProps(field: Field) {
    return fieldErrors[field]
      ? { "aria-invalid": true, "aria-describedby": errorId(field) }
      : {};
  }

  /** Help text always describes the field; an error, when shown, too. */
  function describedBy(field: Field, helpId: string): string {
    return fieldErrors[field] ? `${helpId} ${errorId(field)}` : helpId;
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
            aria-invalid={fieldErrors.name ? true : undefined}
            aria-describedby={describedBy("name", nameHelpId)}
            value={name}
            onChange={(e) => setName(e.target.value)}
            className={filterInputClassName}
          />
          <p id={nameHelpId} className="text-xs text-muted-foreground">
            {t("auth.registerNameHelp")}
          </p>
          <FieldError field="name" message={fieldErrors.name} />
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
            aria-invalid={fieldErrors.display_name ? true : undefined}
            aria-describedby={describedBy("display_name", displayNameHelpId)}
            value={displayName}
            onChange={(e) => setCustomDisplayName(e.target.value)}
            className={filterInputClassName}
          />
          <p id={displayNameHelpId} className="text-xs text-muted-foreground">
            {t("auth.registerDisplayNameHelp")}
          </p>
          <FieldError field="display_name" message={fieldErrors.display_name} />
        </div>
        <div className="grid gap-1.5">
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
              {...invalidProps("email")}
            />
          </label>
          <FieldError field="email" message={fieldErrors.email} />
        </div>
        <div className="grid gap-1.5">
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
              invalid={Boolean(fieldErrors.password)}
              describedBy={
                fieldErrors.password ? errorId("password") : undefined
              }
            />
          </label>
          <FieldError field="password" message={fieldErrors.password} />
        </div>
        <div className="grid gap-1.5">
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
              invalid={Boolean(fieldErrors.password_confirmation)}
              describedBy={
                fieldErrors.password_confirmation
                  ? errorId("password_confirmation")
                  : undefined
              }
            />
          </label>
          <FieldError
            field="password_confirmation"
            message={fieldErrors.password_confirmation}
          />
        </div>
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

/** Not an alert: the form's summary is the one announced; this is read with the field. */
function FieldError({ field, message }: { field: Field; message?: string }) {
  if (!message) {
    return null;
  }

  return (
    <span id={errorId(field)} className="text-sm text-destructive">
      {message}
    </span>
  );
}
