"use client";

import { useState } from "react";
import { filterInputClassName } from "@/components/directory/filter-form";
import { Button } from "@/components/ui/button";
import { PrivacyNote } from "@/components/auth/privacy-note";
import { FormError } from "@/components/ui/form-message";
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

  if (sent) {
    return (
      <div className="grid gap-2">
        <p role="status" className="text-sm text-muted-foreground">
          {t("auth.verifyResendSent")}
        </p>
        <PrivacyNote />
      </div>
    );
  }

  return (
    <form onSubmit={handleSubmit} className="grid gap-3">
      <label className="grid gap-1.5 text-sm">
        <span className="font-semibold text-foreground">{t("auth.email")}</span>
        <input
          type="email"
          name="email"
          autoComplete="email"
          required
          value={email}
          onChange={(event) => setEmail(event.target.value)}
          className={filterInputClassName}
        />
      </label>
      {error ? <FormError>{error}</FormError> : null}
      <Button
        type="submit"
        disabled={pending}
        className="min-h-[44px] w-full sm:w-auto"
      >
        {t("auth.verifyResend")}
      </Button>
    </form>
  );
}
