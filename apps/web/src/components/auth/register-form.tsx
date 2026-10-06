"use client";

import { useEffect, useRef, useState } from "react";
import { AuthStateHeader } from "@/components/auth/auth-page";
import { PasswordField } from "@/components/auth/password-input";
import { PrivacyNote } from "@/components/auth/privacy-note";
import { TextField } from "@/components/auth/text-field";
import { Button, TextLink } from "@/components/ui/button";
import { FormError } from "@/components/ui/form-message";
import { Notice } from "@/components/ui/notice";
import {
  DISPLAY_NAME_MAX_LENGTH,
  suggestDisplayName,
} from "@/lib/display-name";
import { t, type MessageKey } from "@/i18n/t";

type RegisterFormProps = {
  registrationsEnabled: boolean;
};

type Field =
  "name" | "display_name" | "email" | "password" | "password_confirmation";

/** Form order, with each field's visible label (used by the error summary). */
const FIELDS: Array<{ field: Field; label: MessageKey }> = [
  { field: "name", label: "auth.registerName" },
  { field: "display_name", label: "auth.registerDisplayName" },
  { field: "email", label: "auth.email" },
  { field: "password", label: "auth.password" },
  { field: "password_confirmation", label: "auth.registerPasswordConfirm" },
];

/** The first message per field from a Laravel 422 `errors` bag. */
function fieldErrorsFrom(errors: unknown): Partial<Record<Field, string>> {
  const out: Partial<Record<Field, string>> = {};

  if (!errors || typeof errors !== "object") {
    return out;
  }

  for (const { field } of FIELDS) {
    const messages = (errors as Record<string, unknown>)[field];

    if (Array.isArray(messages) && typeof messages[0] === "string") {
      out[field] = messages[0];
    }
  }

  return out;
}

/** Stable ids: the summary links to them; errors are `<id>-error`. */
function fieldId(field: Field): string {
  return `register-${field}`;
}

export function RegisterForm({ registrationsEnabled }: RegisterFormProps) {
  const [name, setName] = useState("");
  // Follows the name ("Марија К.") until the person types their own.
  const [customDisplayName, setCustomDisplayName] = useState<string | null>(
    null,
  );
  const displayName = customDisplayName ?? suggestDisplayName(name);
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
    return <Notice tone="info">{t("auth.registerDisabled")}</Notice>;
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
      <div className="flex flex-col gap-5">
        <AuthStateHeader
          icon="mail"
          title={t("auth.verifyCheckInbox")}
          headingRef={successHeadingRef}
        />
        <p role="status" className="type-body text-ink">
          {t("auth.verifyCheckInboxBody")}
        </p>
        <PrivacyNote />
        <Button href="/login" variant="secondary" size="lg" fullWidth>
          {t("auth.signIn")}
        </Button>
      </div>
    );
  }

  const rejected = FIELDS.filter(({ field }) => fieldErrors[field]);

  return (
    <div className="flex flex-col gap-6">
      <form onSubmit={handleSubmit} className="flex flex-col gap-5">
        <TextField
          id={fieldId("name")}
          label={t("auth.registerName")}
          hint={t("auth.registerNameHelp")}
          error={fieldErrors.name}
          type="text"
          name="name"
          required
          autoComplete="name"
          value={name}
          onChange={(e) => setName(e.target.value)}
        />
        <TextField
          id={fieldId("display_name")}
          label={t("auth.registerDisplayName")}
          hint={t("auth.registerDisplayNameHelp")}
          error={fieldErrors.display_name}
          type="text"
          name="display_name"
          required
          maxLength={DISPLAY_NAME_MAX_LENGTH}
          autoComplete="nickname"
          value={displayName}
          onChange={(e) => setCustomDisplayName(e.target.value)}
        />
        <TextField
          id={fieldId("email")}
          label={t("auth.email")}
          error={fieldErrors.email}
          type="email"
          name="email"
          autoComplete="email"
          required
          value={email}
          onChange={(e) => setEmail(e.target.value)}
        />
        <PasswordField
          id={fieldId("password")}
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
          id={fieldId("password_confirmation")}
          label={t("auth.registerPasswordConfirm")}
          error={fieldErrors.password_confirmation}
          name="password_confirmation"
          autoComplete="new-password"
          required
          value={passwordConfirmation}
          onChange={setPasswordConfirmation}
        />
        {error ? (
          <div className="flex flex-col gap-3 rounded-input border-2 border-ink bg-white p-4">
            <FormError>{error}</FormError>
            {/* With several rejected fields, a link to each (the alert's
                text names only the first). One error needs no list. */}
            {rejected.length > 1 ? (
              <div className="flex flex-col gap-1 pl-7">
                <p className="type-meta text-ink-2">
                  {t("auth.errorSummaryIntro")}
                </p>
                <ul className="flex flex-col">
                  {rejected.map(({ field, label }) => (
                    <li key={field}>
                      <a
                        href={`#${fieldId(field)}`}
                        onClick={(event) => {
                          event.preventDefault();
                          document.getElementById(fieldId(field))?.focus();
                        }}
                        className="link-underline inline-flex min-h-12 items-center type-body font-semibold text-ink"
                      >
                        {t(label)}
                      </a>
                    </li>
                  ))}
                </ul>
              </div>
            ) : null}
          </div>
        ) : null}
        <Button
          type="submit"
          size="lg"
          fullWidth
          loading={pending}
          disabled={pending}
        >
          {pending ? t("auth.registering") : t("auth.register")}
        </Button>
      </form>
      <p className="flex flex-wrap items-center gap-x-2 border-t border-line pt-4 type-body text-ink-2">
        <span>{t("auth.haveAccount")}</span>
        <TextLink href="/login">{t("auth.signIn")}</TextLink>
      </p>
    </div>
  );
}
