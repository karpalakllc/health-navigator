"use client";

import { useRouter } from "next/navigation";
import { useId, useRef, useState } from "react";
import { BottomSheet } from "@/components/ui/bottom-sheet";
import { Button } from "@/components/ui/button";
import { Fieldset, Radio, Textarea } from "@/components/ui/field";
import { FormError } from "@/components/ui/form-message";
import { formatCharCounter } from "@/components/forum/char-counter";
import { loginHref } from "@/lib/auth/login-href";
import {
  REPORT_NOTE_MAX,
  REPORT_REASONS,
  type ReportReason,
  type ReportTarget,
} from "@/lib/api/reports";
import { t, type MessageKey } from "@/i18n/t";

const REASON_LABELS: Record<ReportReason, MessageKey> = {
  spam: "reports.reasonSpam",
  abuse: "reports.reasonAbuse",
  false_information: "reports.reasonFalseInformation",
  personal_data: "reports.reasonPersonalData",
  other: "reports.reasonOther",
};

type ReportButtonProps = {
  target: ReportTarget;
  /** Accessible name naming what is reported („Пријави ја рецензијата од …“). */
  label: string;
  isLoggedIn: boolean;
  /** Where sign-in brings a signed-out visitor back to. */
  returnTo: string;
};

/**
 * „Пријави“: a quiet ghost button at the end of a review or post. Signed out,
 * it is a link to sign-in that returns here. Signed in, it opens a modal sheet
 * (focus trapped, Escape closes, focus returns to this button) with the reason
 * codes as radios and an optional note; a repeat report is harmless (the API
 * keeps the first one).
 */
export function ReportButton({
  target,
  label,
  isLoggedIn,
  returnTo,
}: ReportButtonProps) {
  const [open, setOpen] = useState(false);

  if (!isLoggedIn) {
    return (
      <Button
        href={loginHref(returnTo)}
        variant="ghost"
        size="sm"
        leadingIcon="flag"
        aria-label={label}
        className="text-ink-2"
      >
        {t("reports.action")}
      </Button>
    );
  }

  return (
    <>
      <Button
        type="button"
        variant="ghost"
        size="sm"
        leadingIcon="flag"
        aria-label={label}
        aria-haspopup="dialog"
        className="text-ink-2"
        onClick={() => setOpen(true)}
      >
        {t("reports.action")}
      </Button>
      <BottomSheet
        open={open}
        onClose={() => setOpen(false)}
        title={t("reports.dialogTitle")}
        closeLabel={t("reports.close")}
      >
        {/* Remounted per opening, so a closed dialog starts fresh. */}
        {open ? (
          <ReportForm
            target={target}
            returnTo={returnTo}
            onDone={() => setOpen(false)}
          />
        ) : null}
      </BottomSheet>
    </>
  );
}

function ReportForm({
  target,
  returnTo,
  onDone,
}: {
  target: ReportTarget;
  returnTo: string;
  onDone: () => void;
}) {
  const router = useRouter();
  const formId = useId();
  const [reason, setReason] = useState<ReportReason | null>(null);
  const [note, setNote] = useState("");
  const [reasonError, setReasonError] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [pending, setPending] = useState(false);
  const [sent, setSent] = useState(false);
  const firstRadio = useRef<HTMLDivElement>(null);
  const successRef = useRef<HTMLDivElement>(null);

  async function handleSubmit(event: React.FormEvent) {
    event.preventDefault();
    setError(null);

    if (!reason) {
      setReasonError(t("reports.reasonRequired"));
      firstRadio.current?.querySelector("input")?.focus();
      return;
    }

    setReasonError(null);
    setPending(true);

    try {
      const response = await fetch("/api/reports", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          target,
          reason,
          note: note.trim() === "" ? null : note.trim(),
        }),
      });

      // The session ran out since the page rendered: sign in and come back.
      if (response.status === 401) {
        router.push(loginHref(returnTo));
        return;
      }

      if (!response.ok) {
        const payload = await response.json().catch(() => ({}));
        setError(
          response.status === 429
            ? t("reports.throttled")
            : (payload.message ?? t("reports.error")),
        );
        return;
      }

      setSent(true);
      // Focus follows the content swap, so it is not lost on <body>.
      requestAnimationFrame(() => successRef.current?.focus());
    } catch {
      setError(t("reports.error"));
    } finally {
      setPending(false);
    }
  }

  if (sent) {
    return (
      <div
        ref={successRef}
        tabIndex={-1}
        role="status"
        className="flex flex-col items-start gap-3 pt-2 outline-none"
      >
        <p className="type-h3 text-ink">{t("reports.successTitle")}</p>
        <p className="type-body text-ink-2">{t("reports.successBody")}</p>
        <Button type="button" variant="secondary" onClick={onDone}>
          {t("reports.close")}
        </Button>
      </div>
    );
  }

  return (
    <form
      id={formId}
      noValidate
      onSubmit={handleSubmit}
      className="flex flex-col gap-5 pt-2"
    >
      <p className="type-body text-ink-2">{t("reports.intro")}</p>
      <Fieldset legend={t("reports.reasonLegend")} error={reasonError}>
        {REPORT_REASONS.map((code, index) => (
          <div key={code} ref={index === 0 ? firstRadio : undefined}>
            <Radio
              name={`${formId}-reason`}
              value={code}
              label={t(REASON_LABELS[code])}
              checked={reason === code}
              onChange={() => {
                setReason(code);
                setReasonError(null);
              }}
            />
          </div>
        ))}
      </Fieldset>
      <Textarea
        label={t("reports.noteLabel")}
        value={note}
        maxLength={REPORT_NOTE_MAX}
        rows={3}
        onChange={(event) => setNote(event.target.value)}
        counter={formatCharCounter(note.length, REPORT_NOTE_MAX)}
      />
      {error ? <FormError>{error}</FormError> : null}
      <Button type="submit" loading={pending} fullWidth>
        {t("reports.submit")}
      </Button>
    </form>
  );
}
