"use client";

import { useRouter } from "next/navigation";
import { useEffect, useRef, useState } from "react";
import { TextField } from "@/components/auth/text-field";
import { Button } from "@/components/ui/button";
import { FormError } from "@/components/ui/form-message";
import { Icon } from "@/components/ui/icons";
import { t } from "@/i18n/t";

/**
 * Account data export with password step-up (same confirmation pattern as
 * delete). On success the JSON attachment is saved via a blob download.
 */
export function AccountExport() {
  const router = useRouter();
  const [password, setPassword] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [fieldError, setFieldError] = useState<string | null>(null);
  const [pending, setPending] = useState(false);
  const formRef = useRef<HTMLFormElement>(null);

  useEffect(() => {
    if (fieldError) {
      formRef.current
        ?.querySelector<HTMLInputElement>('input[name="password"]')
        ?.focus();
    }
  }, [fieldError]);

  async function handleSubmit(event: React.FormEvent) {
    event.preventDefault();
    setError(null);
    setFieldError(null);

    if (password === "") {
      setFieldError(t("account.data.exportPasswordRequired"));
      return;
    }

    setPending(true);

    try {
      const response = await fetch("/api/account/export", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ password }),
      });

      if (response.ok) {
        const blob = await response.blob();
        const disposition = response.headers.get("content-disposition") ?? "";
        const match = /filename="([^"]+)"/i.exec(disposition);
        const filename = match?.[1] ?? "zdravje360.json";
        const url = URL.createObjectURL(blob);
        const link = document.createElement("a");
        link.href = url;
        link.download = filename;
        link.click();
        URL.revokeObjectURL(url);
        setPassword("");
        return;
      }

      if (response.status === 401) {
        router.push("/login?redirect=/account/data");
        return;
      }

      const payload = (await response.json().catch(() => null)) as {
        message?: string;
        errors?: { password?: string[] };
      } | null;

      const passwordError = payload?.errors?.password?.[0];

      if (passwordError) {
        setFieldError(passwordError);
        setPassword("");
        return;
      }

      if (response.status === 429) {
        setError(t("account.data.exportThrottled"));
        return;
      }

      setError(payload?.message ?? t("account.data.exportError"));
    } catch {
      setError(t("account.data.exportError"));
    } finally {
      setPending(false);
    }
  }

  return (
    <form
      ref={formRef}
      noValidate
      onSubmit={handleSubmit}
      className="flex flex-col gap-4"
    >
      <p className="type-body text-ink-2 measure">
        {t("account.data.exportPasswordHint")}
      </p>
      <TextField
        label={t("account.data.exportPassword")}
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
      <Button
        type="submit"
        loading={pending}
        disabled={pending}
        className="w-full sm:w-auto sm:self-start"
      >
        <Icon name="file-text" size={20} />
        <span>
          {pending
            ? t("account.data.exporting")
            : t("account.data.exportAction")}
        </span>
      </Button>
    </form>
  );
}
