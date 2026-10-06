"use client";

import { useId, useState } from "react";
import { Button } from "@/components/ui/button";
import { Input, Textarea } from "@/components/ui/field";
import { FormError, FormSuccess } from "@/components/ui/form-message";
import { t } from "@/i18n/t";

type ClaimErrors = { message?: string; contact?: string };

/**
 * „Ова е мој профил“: a short message and a contact so staff can verify the
 * person outside the platform. No documents. Sent once; staff follow up.
 */
export function DoctorClaimForm({ slug }: { slug: string }) {
  const [message, setMessage] = useState("");
  const [contact, setContact] = useState("");
  const [pending, setPending] = useState(false);
  const [errors, setErrors] = useState<ClaimErrors>({});
  const [error, setError] = useState<string | null>(null);
  const [sent, setSent] = useState(false);
  const errorId = useId();

  async function handleSubmit(event: React.FormEvent) {
    event.preventDefault();
    setError(null);

    const local: ClaimErrors = {};

    if (message.trim().length < 10) {
      local.message = t("doctorDashboard.claimMessageShort");
    }

    if (contact.trim().length < 5) {
      local.contact = t("doctorDashboard.claimContactShort");
    }

    setErrors(local);

    if (local.message || local.contact) {
      return;
    }

    setPending(true);

    try {
      const response = await fetch("/api/doctor-dashboard/claim", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ slug, message, contact }),
      });
      const payload = (await response.json().catch(() => null)) as {
        message?: string;
        errors?: { message?: string[]; contact?: string[] };
      } | null;

      if (!response.ok) {
        setErrors({
          message: payload?.errors?.message?.[0],
          contact: payload?.errors?.contact?.[0],
        });
        setError(payload?.message ?? t("doctorDashboard.claimError"));

        return;
      }

      setSent(true);
    } catch {
      setError(t("doctorDashboard.claimError"));
    } finally {
      setPending(false);
    }
  }

  return (
    <div className="flex flex-col gap-5">
      {/* Mounted from the start so the confirmation is announced. */}
      <FormSuccess>{sent ? t("doctorDashboard.claimSent") : null}</FormSuccess>
      {sent ? null : (
        <form
          noValidate
          onSubmit={handleSubmit}
          className="flex flex-col gap-5"
        >
          <Textarea
            label={t("doctorDashboard.claimMessage")}
            hint={t("doctorDashboard.claimMessageHint")}
            name="message"
            rows={4}
            required
            maxLength={1000}
            value={message}
            error={errors.message}
            onChange={(event) => setMessage(event.target.value)}
          />
          <Input
            label={t("doctorDashboard.claimContact")}
            name="contact"
            required
            maxLength={255}
            autoComplete="tel"
            value={contact}
            error={errors.contact}
            onChange={(event) => setContact(event.target.value)}
          />
          {error ? <FormError id={errorId}>{error}</FormError> : null}
          <Button
            type="submit"
            loading={pending}
            disabled={pending}
            className="w-full sm:w-auto sm:self-start"
          >
            {pending
              ? t("doctorDashboard.claimSubmitting")
              : t("doctorDashboard.claimSubmit")}
          </Button>
        </form>
      )}
    </div>
  );
}
