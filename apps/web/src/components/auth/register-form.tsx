"use client";

import { useCallback, useEffect, useRef, useState } from "react";
import { AuthStateHeader } from "@/components/auth/auth-page";
import { PasswordField } from "@/components/auth/password-input";
import { PrivacyNote } from "@/components/auth/privacy-note";
import { TextField } from "@/components/auth/text-field";
import { Button, TextLink } from "@/components/ui/button";
import { ErrorSummary } from "@/components/ui/error-summary";
import { FormError } from "@/components/ui/form-message";
import { Notice } from "@/components/ui/notice";
import { TermsConsent } from "@/components/usernames/terms-consent";
import {
  UsernameField,
  type UsernameAvailability,
} from "@/components/usernames/username-field";
import { normalizeUsername, usernameFormatError } from "@/lib/username";
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
  | "name"
  | "username"
  | "email"
  | "password"
  | "password_confirmation"
  | "accept_terms";

/** Form order, with each field's visible label (used by the error summary). */
const FIELDS: Array<{ field: Field; label: MessageKey }> = [
  { field: "name", label: "auth.registerName" },
  { field: "username", label: "usernames.label" },
  { field: "email", label: "auth.email" },
  { field: "password", label: "auth.password" },
  { field: "password_confirmation", label: "auth.registerPasswordConfirm" },
  { field: "accept_terms", label: "usernames.termsSummaryLabel" },
];

const FIELD_NAMES = FIELDS.map(({ field }) => field);

/**
 * Every field's errors from a Laravel 422 `errors` bag, in our words, under
 * the right field: the API files the „confirmed“ rule under `password`, but
 * the person has to fix the confirmation field.
 */
function fieldErrorsFrom(errors: unknown): Partial<Record<Field, string>> {
  const mapped = mapApiFieldErrors(errors, FIELD_NAMES, {
    passwordField: "password",
    confirmationField: "password_confirmation",
  });

  // The checkbox says what to do in its own words, whatever the API wrote.
  if (mapped.accept_terms) {
    mapped.accept_terms = t("usernames.termsRequired");
  }

  return mapped;
}

/** Stable ids: the summary links to them; errors are `<id>-error`. */
function fieldId(field: Field): string {
  return `register-${field}`;
}

export function RegisterForm({ registrationsEnabled }: RegisterFormProps) {
  const [name, setName] = useState("");
  const [username, setUsername] = useState("");
  const [availability, setAvailability] = useState<UsernameAvailability>({
    state: "idle",
  });
  const [acceptTerms, setAcceptTerms] = useState(false);
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

  const onAvailability = useCallback(
    (next: UsernameAvailability) => setAvailability(next),
    [],
  );

  if (!registrationsEnabled) {
    return <Notice tone="info">{t("auth.registerDisabled")}</Notice>;
  }

  async function handleSubmit(event: React.FormEvent) {
    event.preventDefault();
    setError(null);

    const usernameProblem = usernameFormatError(username);
    const local = compactErrors<Field>({
      name: requiredError(name),
      username:
        requiredError(username) ??
        (usernameProblem ? t(usernameProblem) : null) ??
        (availability.state === "unavailable" &&
        availability.username === normalizeUsername(username)
          ? availability.message
          : null),
      email: emailError(email),
      password: requiredError(password),
      password_confirmation: passwordConfirmationError(
        password,
        passwordConfirmation,
      ),
      accept_terms: acceptTerms ? null : t("usernames.termsRequired"),
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
          username: normalizeUsername(username),
          email,
          password,
          password_confirmation: passwordConfirmation,
          accept_terms: acceptTerms,
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
        <UsernameField
          id={fieldId("username")}
          hint={t("usernames.registerHelp")}
          error={fieldErrors.username}
          value={username}
          onChange={setUsername}
          onAvailability={onAvailability}
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
        <TermsConsent
          id={fieldId("accept_terms")}
          checked={acceptTerms}
          onChange={setAcceptTerms}
          error={fieldErrors.accept_terms}
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
