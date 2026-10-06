"use client";

import { useId, useRef, useState } from "react";
import { Button } from "@/components/ui/button";
import { Input, Select, Textarea } from "@/components/ui/field";
import { FormError, FormSuccess } from "@/components/ui/form-message";
import {
  CORRECTION_CONTACT_MAX,
  CORRECTION_FIELDS,
  CORRECTION_HONEYPOT,
  CORRECTION_MESSAGE_MAX,
  CORRECTION_MESSAGE_MIN,
  type CorrectionSubject,
  type CorrectionType,
} from "@/lib/api/corrections";
import { t, tFormat, type MessageKey } from "@/i18n/t";

type Errors = { field?: string; message?: string; contact?: string };

type ApiPayload = {
  data?: { message?: string };
  message?: string;
  errors?: { field?: string[]; message?: string[]; contact?: string[] };
} | null;

/** Loose check only; the API validates the address properly. */
const EMAIL_SHAPE = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

/**
 * „Пријави грешка во профилот“ (type correction: which part, what is wrong,
 * an optional e-mail for a reply) or the listed doctor's „Барање за приговор /
 * отстранување“ (type objection: who they are and what they ask, and a
 * required phone or e-mail for verification). Open to everyone; the hidden
 * honeypot field is for bots only.
 */
export function ProfileCorrectionForm({
  subject,
  slug,
  type,
}: {
  subject: CorrectionSubject;
  slug: string;
  type: CorrectionType;
}) {
  const objection = type === "objection";
  const [field, setField] = useState("");
  const [message, setMessage] = useState("");
  const [contact, setContact] = useState("");
  const [pending, setPending] = useState(false);
  const [errors, setErrors] = useState<Errors>({});
  const [error, setError] = useState<string | null>(null);
  const [sent, setSent] = useState<string | null>(null);
  const honeypot = useRef<HTMLInputElement>(null);
  const errorId = useId();
  const honeypotId = useId();

  function validate(): Errors {
    const local: Errors = {};

    if (!objection && field === "") {
      local.field = t("corrections.fieldRequired");
    }

    if (message.trim().length < CORRECTION_MESSAGE_MIN) {
      local.message = t("corrections.messageShort");
    }

    const trimmed = contact.trim();

    if (objection && trimmed.length < 5) {
      local.contact = t("corrections.objectionContactShort");
    } else if (!objection && trimmed !== "" && !EMAIL_SHAPE.test(trimmed)) {
      local.contact = t("corrections.contactInvalid");
    }

    return local;
  }

  async function handleSubmit(event: React.FormEvent) {
    event.preventDefault();
    setError(null);

    const local = validate();
    setErrors(local);

    if (local.field || local.message || local.contact) {
      return;
    }

    setPending(true);

    try {
      const response = await fetch("/api/corrections", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          subject,
          slug,
          type,
          field: objection ? null : field,
          message,
          contact: contact.trim() === "" ? null : contact.trim(),
          [CORRECTION_HONEYPOT]: honeypot.current?.value ?? "",
        }),
      });
      const payload = (await response.json().catch(() => null)) as ApiPayload;

      if (!response.ok) {
        setErrors({
          field: payload?.errors?.field?.[0],
          message: payload?.errors?.message?.[0],
          contact: payload?.errors?.contact?.[0],
        });
        setError(
          response.status === 429
            ? t("corrections.throttled")
            : (payload?.message ??
                t(
                  objection
                    ? "corrections.objectionError"
                    : "corrections.error",
                )),
        );

        return;
      }

      setSent(
        payload?.data?.message ??
          t(objection ? "corrections.objectionSent" : "corrections.sent"),
      );
    } catch {
      setError(
        t(objection ? "corrections.objectionError" : "corrections.error"),
      );
    } finally {
      setPending(false);
    }
  }

  return (
    <div className="flex flex-col gap-5">
      {/* Mounted from the start so the confirmation is announced. */}
      <FormSuccess>{sent}</FormSuccess>
      {sent ? null : (
        <form
          noValidate
          onSubmit={handleSubmit}
          className="relative flex flex-col gap-5"
        >
          {objection ? null : (
            <Select
              label={t("corrections.fieldLabel")}
              name="field"
              required
              value={field}
              error={errors.field}
              onChange={(event) => setField(event.target.value)}
            >
              <option value="">{t("corrections.fieldPlaceholder")}</option>
              {CORRECTION_FIELDS[subject].map((code) => (
                <option key={code} value={code}>
                  {t(`corrections.fields.${code}` as MessageKey)}
                </option>
              ))}
            </Select>
          )}
          <Textarea
            label={t(
              objection
                ? "corrections.objectionMessageLabel"
                : "corrections.messageLabel",
            )}
            hint={t(
              objection
                ? "corrections.objectionMessageHint"
                : "corrections.messageHint",
            )}
            name="message"
            rows={5}
            required
            maxLength={CORRECTION_MESSAGE_MAX}
            value={message}
            error={errors.message}
            counter={tFormat("corrections.messageCounter", {
              count: String(message.length),
              max: String(CORRECTION_MESSAGE_MAX),
            })}
            onChange={(event) => setMessage(event.target.value)}
          />
          <Input
            label={t(
              objection
                ? "corrections.objectionContactLabel"
                : "corrections.contactLabel",
            )}
            hint={t(
              objection
                ? "corrections.objectionContactHint"
                : "corrections.contactHint",
            )}
            name="contact"
            type={objection ? "text" : "email"}
            // Phone or e-mail for an objection: no single autofill token fits.
            autoComplete={objection ? "off" : "email"}
            inputMode={objection ? undefined : "email"}
            required={objection}
            maxLength={CORRECTION_CONTACT_MAX}
            value={contact}
            error={errors.contact}
            onChange={(event) => setContact(event.target.value)}
          />
          {/* Honeypot: out of sight and out of the tab order, hidden from
              assistive tech. People never fill it; a bot that does is
              answered like a success and nothing is stored. */}
          <div
            aria-hidden="true"
            className="pointer-events-none absolute -left-[10000px] top-0 size-px overflow-hidden"
          >
            <label htmlFor={honeypotId}>{t("corrections.honeypotLabel")}</label>
            <input
              ref={honeypot}
              id={honeypotId}
              type="text"
              name={CORRECTION_HONEYPOT}
              tabIndex={-1}
              autoComplete="off"
              defaultValue=""
            />
          </div>
          {error ? <FormError id={errorId}>{error}</FormError> : null}
          <Button
            type="submit"
            loading={pending}
            disabled={pending}
            className="w-full sm:w-auto sm:self-start"
          >
            {pending
              ? t("corrections.submitting")
              : t(
                  objection
                    ? "corrections.objectionSubmit"
                    : "corrections.submit",
                )}
          </Button>
        </form>
      )}
    </div>
  );
}
