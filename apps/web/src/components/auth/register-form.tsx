"use client";

import { useEffect, useRef, useState } from "react";
import { AuthStateHeader } from "@/components/auth/auth-page";
import { PasswordField } from "@/components/auth/password-input";
import { PrivacyNote } from "@/components/auth/privacy-note";
import { TextField } from "@/components/auth/text-field";
import { Button, TextLink } from "@/components/ui/button";
import { ErrorSummary } from "@/components/ui/error-summary";
import { FormError } from "@/components/ui/form-message";
import { Notice } from "@/components/ui/notice";
import {
  DISPLAY_NAME_MAX_LENGTH,
  suggestDisplayName,
} from "@/lib/display-name";
import {
  compactErrors,
  emailError,
  mapApiFieldErrors,
  passwordConfirmationError,
  requiredError,
} from "@/lib/form-validation";
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

const FIELD_NAMES = FIELDS.map(({ field }) => field);

/**
 * Every field's errors from a Laravel 422 `errors` bag, in our words, under
 * the right field: the API files the „confirmed“ rule under `password`, but
 * the person has to fix the confirmation field.
 */
function fieldErrorsFrom(errors: unknown): Partial<Record<Field, string>> {
  return mapApiFieldErrors(errors, FIELD_NAMES, {
    passwordField: "password",
    confirmationField: "password_confirmation",
  });
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
  const summaryRef = useRef<HTMLDivElement>(null);
  // Bumped on every rejected submit so focus moves to the summary each time.
  const [rejections, setRejections] = useState(0);

  useEffect(() => {
    if (rejections > 0) {
      summaryRef.current?.focus();
    }
  }, [rejections]);

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

    const local = compactErrors<Field>({
      name: requiredError(name),
      display_name: requiredError(displayName),
      email: emailError(email),
      password: requiredError(password),
      password_confirmation: passwordConfirmationError(
        password,
        passwordConfirmation,
      ),
    });

    setFieldErrors(local);

    if (Object.keys(local).length > 0) {
      setRejections((count) => count + 1);
      return;
    }

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
        // Each field shows its own message and the summary lists them all
        // (Laravel's "… (и уште N грешки)" names only the first one). A
        // reply without field errors (rate limit, server) keeps its message.
        const mapped = fieldErrorsFrom(payload.errors);
        setFieldErrors(mapped);

        if (Object.keys(mapped).length > 0) {
          setRejections((count) => count + 1);
        } else {
          setError(payload.message ?? t("auth.registerFailed"));
        }
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
      <form noValidate onSubmit={handleSubmit} className="flex flex-col gap-5">
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
        {/* Every rejected field, named and linked, with its message. */}
        {rejected.length > 0 ? (
          <ErrorSummary
            ref={summaryRef}
            items={rejected.map(({ field, label }) => ({
              id: fieldId(field),
              label: t(label),
              message: fieldErrors[field] ?? "",
            }))}
          />
        ) : null}
        {error ? <FormError>{error}</FormError> : null}
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
