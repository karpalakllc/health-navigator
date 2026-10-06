"use client";

import { useState } from "react";
import { PrivacyNote } from "@/components/auth/privacy-note";
import { TextField } from "@/components/auth/text-field";
import { Button } from "@/components/ui/button";
import { FormError, FormSuccess } from "@/components/ui/form-message";
import { t } from "@/i18n/t";

/**
 * Requests a fresh verification link.
 *
 * Always reports the same thing back, matching the API — telling the visitor
 * "no such account" here would undo the reason registration is non-committal.
 */
export function ResendVerificationForm({
  defaultEmail = "",
}: {
  defaultEmail?: string;
}) {
  const [email, setEmail] = useState(defaultEmail);
  const [pending, setPending] = useState(false);
  const [sent, setSent] = useState(false);
  const [error, setError] = useState<string | null>(null);

  async function handleSubmit(event: React.FormEvent) {
    event.preventDefault();
    setError(null);
    setPending(true);

    // "Sent" only when the API accepted the request. Its accepted reply is the
    // same for every address, so this reveals nothing — but a 429 or a network
    // failure must not be reported as a sent link.
    try {
      const response = await fetch("/api/session/resend-verification", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ email }),
      });

      if (!response.ok) {
        const payload = (await response.json().catch(() => null)) as {
          message?: string;
        } | null;
        setError(payload?.message ?? t("auth.verifyResendFailed"));
        return;
      }

      setSent(true);
    } catch {
      setError(t("auth.verifyResendFailed"));
    } finally {
      setPending(false);
    }
  }

  // The status region stays mounted across the swap from form to confirmation,
  // so the confirmation is announced (see FormSuccess).
  return (
    <div className="flex flex-col gap-4">
      <FormSuccess tone="muted">
        {sent ? t("auth.verifyResendSent") : null}
      </FormSuccess>
      {sent ? (
        <PrivacyNote />
      ) : (
        <form onSubmit={handleSubmit} className="flex flex-col gap-5">
          <TextField
            label={t("auth.email")}
            type="email"
            name="email"
            autoComplete="email"
            required
            value={email}
            onChange={(event) => setEmail(event.target.value)}
          />
          {error ? <FormError>{error}</FormError> : null}
          <Button
            type="submit"
            size="lg"
            fullWidth
            loading={pending}
            disabled={pending}
          >
            {t("auth.verifyResend")}
          </Button>
        </form>
      )}
    </div>
  );
}
