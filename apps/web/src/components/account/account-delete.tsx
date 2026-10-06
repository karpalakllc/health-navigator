"use client";

import { useRouter } from "next/navigation";
import { useEffect, useId, useRef, useState } from "react";
import { TextField } from "@/components/auth/text-field";
import { Button } from "@/components/ui/button";
import { FormError } from "@/components/ui/form-message";
import { t } from "@/i18n/t";

/**
 * Account deletion, in two deliberate steps: „Избриши ја сметката“ opens a
 * confirmation that asks for the password, and only the explicit
 * „Трајно избриши ја сметката“ button sends it.
 *
 * Focus follows the flow: opening moves focus to the confirmation's heading
 * (so a screen reader announces what is being confirmed), a rejected password
 * returns focus to the field with its error attached, and cancelling returns
 * focus to the button that opened it.
 */
export function AccountDelete() {
  const router = useRouter();
  const [open, setOpen] = useState(false);
  const [password, setPassword] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [fieldError, setFieldError] = useState<string | null>(null);
  const [pending, setPending] = useState(false);
  // The shared Button and TextField do not forward refs, so focus targets are
  // found inside wrappers this component owns.
  const startRef = useRef<HTMLDivElement>(null);
  const headingRef = useRef<HTMLHeadingElement>(null);
  const formRef = useRef<HTMLFormElement>(null);
  const restoreFocus = useRef(false);
  const headingId = useId();
  const bodyId = useId();

  useEffect(() => {
    if (open) {
      headingRef.current?.focus();
    } else if (restoreFocus.current) {
      restoreFocus.current = false;
      startRef.current?.querySelector("button")?.focus();
    }
  }, [open]);

  // After the error has rendered, so the field is focused with its error
  // already attached as its description and both are read together.
  useEffect(() => {
    if (fieldError) {
      formRef.current
        ?.querySelector<HTMLInputElement>('input[name="password"]')
        ?.focus();
    }
  }, [fieldError]);

  function cancel() {
    setPassword("");
    setError(null);
    setFieldError(null);
    restoreFocus.current = true;
    setOpen(false);
  }

  async function handleSubmit(event: React.FormEvent) {
    event.preventDefault();
    setError(null);
    setFieldError(null);

    if (password === "") {
      setFieldError(t("account.data.deletePasswordRequired"));
      return;
    }

    setPending(true);

    try {
      const response = await fetch("/api/account/delete", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ password }),
      });

      const payload = (await response.json().catch(() => null)) as {
        message?: string;
        errors?: { password?: string[] };
      } | null;

      if (response.ok) {
        router.replace("/account/deleted");
        router.refresh();
        return;
      }

      if (response.status === 401) {
        router.push("/login?redirect=/account/data");
        return;
      }

      const passwordError = payload?.errors?.password?.[0];

      if (passwordError) {
        setFieldError(passwordError);
        setPassword("");
        return;
      }

      setError(payload?.message ?? t("account.data.deleteError"));
    } catch {
      setError(t("account.data.deleteError"));
    } finally {
      setPending(false);
    }
  }

  if (!open) {
    return (
      <div ref={startRef} className="flex">
        <Button
          variant="secondary"
          onClick={() => setOpen(true)}
          className="w-full sm:w-auto"
        >
          {t("account.data.deleteStart")}
        </Button>
      </div>
    );
  }

  return (
    <form
      ref={formRef}
      noValidate
      onSubmit={handleSubmit}
      aria-labelledby={headingId}
      aria-describedby={bodyId}
      className="flex flex-col gap-4 rounded-[20px] bg-chip-tint p-5"
    >
      <h3
        ref={headingRef}
        id={headingId}
        tabIndex={-1}
        className="type-h3 text-ink"
      >
        {t("account.data.deleteConfirmHeading")}
      </h3>
      <p id={bodyId} className="type-body text-ink measure">
        {t("account.data.deleteConfirmBody")}
      </p>
      <TextField
        label={t("account.data.deletePassword")}
        type="password"
        name="password"
        autoComplete="current-password"
        required
        value={password}
        error={fieldError}
        onChange={(e) => {
          setPassword(e.target.value);
          setFieldError(null);
        }}
        className="max-w-md"
      />
      {error ? <FormError>{error}</FormError> : null}
      <div className="flex flex-col gap-3 sm:flex-row">
        <Button
          type="submit"
          loading={pending}
          disabled={pending}
          className="w-full sm:w-auto"
        >
          {pending
            ? t("account.data.deleting")
            : t("account.data.deleteConfirm")}
        </Button>
        <Button
          variant="ghost"
          onClick={cancel}
          disabled={pending}
          className="w-full sm:w-auto"
        >
          {t("account.data.deleteCancel")}
        </Button>
      </div>
    </form>
  );
}
