"use client";

import { useId, useRef, useState } from "react";
import { useAltcha } from "@/components/altcha/use-altcha";
import { formatCharCounter } from "@/components/forum/char-counter";
import { BottomSheet } from "@/components/ui/bottom-sheet";
import { Button, IconButton } from "@/components/ui/button";
import { Fieldset, Radio, Textarea } from "@/components/ui/field";
import { FormError } from "@/components/ui/form-message";
import {
  PROFILE_REPORT_HONEYPOT,
  PROFILE_REPORT_NOTE_MAX,
  PROFILE_REPORT_REASONS,
  type ProfileReportReason,
  type ProfileReportSubject,
} from "@/lib/api/profile-reports";
import { cn } from "@/lib/cn";
import { t, type MessageKey } from "@/i18n/t";

function reasonLabel(
  reason: ProfileReportReason,
  subject: ProfileReportSubject,
): MessageKey {
  // A facility or pharmacy does not „work here“; it closed or moved.
  if (reason === "no_longer_here" && subject !== "doctor") {
    return "profileReports.reasons.no_longer_here_place";
  }

  return `profileReports.reasons.${reason}`;
}

type ProfileReportButtonProps = {
  subject: ProfileReportSubject;
  slug: string;
  /**
   * Icon only (44 px, for a slot beside the profile title). Without it the
   * flag carries its label too, for a quiet line of its own.
   */
  compact?: boolean;
  className?: string;
};

/**
 * „Пријави профил“ (W7-C): a quiet flag on doctor, facility and pharmacy
 * profiles that opens a modal sheet (focus trapped, Escape closes, focus
 * returns here) with the reasons as radios and an optional note. Anyone can
 * use it, signed in or not; an invisible ALTCHA proof of work and a honeypot
 * keep bots out. The profile is never hidden by reports: staff decide.
 */
export function ProfileReportButton({
  subject,
  slug,
  compact = false,
  className,
}: ProfileReportButtonProps) {
  const [open, setOpen] = useState(false);
  const label = t("profileReports.action");

  return (
    <>
      {compact ? (
        <IconButton
          icon="flag"
          label={label}
          size={44}
          title={label}
          aria-haspopup="dialog"
          className={cn("text-ink-2", className)}
          onClick={() => setOpen(true)}
        />
      ) : (
        <Button
          type="button"
          variant="ghost"
          size="sm"
          leadingIcon="flag"
          aria-haspopup="dialog"
          className={cn("self-end text-ink-2", className)}
          onClick={() => setOpen(true)}
        >
          {label}
        </Button>
      )}
      <BottomSheet
        open={open}
        onClose={() => setOpen(false)}
        title={t("profileReports.dialogTitle")}
        closeLabel={t("profileReports.close")}
      >
        {/* Remounted per opening: a closed sheet starts fresh, and the
            proof of work starts only when someone means to report. */}
        {open ? (
          <ProfileReportForm
            subject={subject}
            slug={slug}
            onDone={() => setOpen(false)}
          />
        ) : null}
      </BottomSheet>
    </>
  );
}

type ApiPayload = {
  data?: { message?: string };
  message?: string;
  errors?: { reason?: string[]; note?: string[] };
} | null;

function ProfileReportForm({
  subject,
  slug,
  onDone,
}: {
  subject: ProfileReportSubject;
  slug: string;
  onDone: () => void;
}) {
  const formId = useId();
  const honeypotId = useId();
  const [reason, setReason] = useState<ProfileReportReason | null>(null);
  const [note, setNote] = useState("");
  const [reasonError, setReasonError] = useState<string | null>(null);
  const [noteError, setNoteError] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [pending, setPending] = useState(false);
  const [sent, setSent] = useState<string | null>(null);
  const firstRadio = useRef<HTMLDivElement>(null);
  const successRef = useRef<HTMLDivElement>(null);
  const errorRef = useRef<HTMLDivElement>(null);
  const honeypot = useRef<HTMLInputElement>(null);
  const altcha = useAltcha();

  function showError(message: string) {
    setError(message);
    requestAnimationFrame(() =>
      errorRef.current?.querySelector<HTMLElement>('[role="alert"]')?.focus(),
    );
  }

  async function handleSubmit(event: React.FormEvent) {
    event.preventDefault();
    setError(null);

    if (!reason) {
      setReasonError(t("profileReports.reasonRequired"));
      firstRadio.current?.querySelector("input")?.focus();
      return;
    }

    setReasonError(null);
    setNoteError(null);
    setPending(true);

    try {
      const altchaPayload = await altcha.solve();

      if (altchaPayload === null) {
        showError(t("altcha.failed"));
        return;
      }

      const response = await fetch("/api/profile-reports", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          subject,
          slug,
          reason,
          note: note.trim() === "" ? null : note.trim(),
          altcha: altchaPayload,
          [PROFILE_REPORT_HONEYPOT]: honeypot.current?.value ?? "",
        }),
      });
      const payload = (await response.json().catch(() => null)) as ApiPayload;

      if (!response.ok) {
        altcha.renew();

        if (payload?.errors?.reason?.[0]) {
          setReasonError(payload.errors.reason[0]);
        }

        if (payload?.errors?.note?.[0]) {
          setNoteError(payload.errors.note[0]);
        }

        showError(
          response.status === 429
            ? t("profileReports.throttled")
            : (payload?.message ?? t("profileReports.error")),
        );
        return;
      }

      setSent(payload?.data?.message ?? t("profileReports.successBody"));
      // Focus follows the content swap, so it is not lost on <body>.
      requestAnimationFrame(() => successRef.current?.focus());
    } catch {
      altcha.renew();
      showError(t("profileReports.error"));
    } finally {
      setPending(false);
    }
  }

  if (sent !== null) {
    return (
      <div
        ref={successRef}
        tabIndex={-1}
        role="status"
        className="flex flex-col items-start gap-3 pt-2 outline-none"
      >
        <p className="type-h3 text-ink">{t("profileReports.successTitle")}</p>
        <p className="type-body text-ink-2">{sent}</p>
        <Button type="button" variant="secondary" onClick={onDone}>
          {t("profileReports.close")}
        </Button>
      </div>
    );
  }

  return (
    <form
      id={formId}
      noValidate
      onSubmit={handleSubmit}
      className="relative flex flex-col gap-5 pt-2"
    >
      <p className="type-body text-ink-2">{t("profileReports.intro")}</p>
      <Fieldset legend={t("profileReports.reasonLegend")} error={reasonError}>
        {PROFILE_REPORT_REASONS.map((code, index) => (
          <div key={code} ref={index === 0 ? firstRadio : undefined}>
            <Radio
              name={`${formId}-reason`}
              value={code}
              label={t(reasonLabel(code, subject))}
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
        label={t("profileReports.noteLabel")}
        hint={t("profileReports.noteHint")}
        name="note"
        value={note}
        maxLength={PROFILE_REPORT_NOTE_MAX}
        rows={3}
        error={noteError}
        onChange={(event) => setNote(event.target.value)}
        counter={formatCharCounter(note.length, PROFILE_REPORT_NOTE_MAX)}
      />
      {/* Honeypot: out of sight and out of the tab order, hidden from
          assistive tech. People never fill it; a bot that does is answered
          like a success and nothing is stored. */}
      <div
        aria-hidden="true"
        className="pointer-events-none absolute -left-[10000px] top-0 size-px overflow-hidden"
      >
        <label htmlFor={honeypotId}>{t("profileReports.honeypotLabel")}</label>
        <input
          ref={honeypot}
          id={honeypotId}
          type="text"
          name={PROFILE_REPORT_HONEYPOT}
          tabIndex={-1}
          autoComplete="off"
          defaultValue=""
        />
      </div>
      <div ref={errorRef}>
        {error ? <FormError tabIndex={-1}>{error}</FormError> : null}
      </div>
      {altcha.widget}
      <Button type="submit" loading={pending} disabled={pending} fullWidth>
        {pending ? t("profileReports.sending") : t("profileReports.submit")}
      </Button>
    </form>
  );
}
