"use client";

import { useRouter } from "next/navigation";
import { useId, useState } from "react";
import { filterInputClassName } from "@/components/directory/filter-form";
import { Button } from "@/components/ui/button";
import { FormError, FormSuccess } from "@/components/ui/form-message";
import {
  DISPLAY_NAME_MAX_LENGTH,
  isValidDisplayName,
  normalizeDisplayName,
} from "@/lib/display-name";
import { t } from "@/i18n/t";

/** Edits the public display name; the private name is not editable here. */
export function AccountDisplayNameForm({
  displayName,
}: {
  displayName: string;
}) {
  const router = useRouter();
  const [value, setValue] = useState(displayName);
  const [pending, setPending] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [saved, setSaved] = useState(false);
  const inputId = useId();
  const helpId = useId();

  async function handleSubmit(event: React.FormEvent) {
    event.preventDefault();
    setError(null);
    setSaved(false);

    if (!isValidDisplayName(value)) {
      setError(t("account.displayNameInvalid"));

      return;
    }

    setPending(true);

    try {
      const response = await fetch("/api/session/profile", {
        method: "PATCH",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ display_name: normalizeDisplayName(value) }),
      });

      const payload = (await response.json().catch(() => null)) as {
        message?: string;
        errors?: { display_name?: string[] };
        data?: { user?: { display_name?: string } };
      } | null;

      if (!response.ok) {
        setError(
          payload?.errors?.display_name?.[0] ??
            payload?.message ??
            t("account.displayNameError"),
        );

        return;
      }

      setValue(
        payload?.data?.user?.display_name ?? normalizeDisplayName(value),
      );
      setSaved(true);
      router.refresh();
    } catch {
      setError(t("account.displayNameError"));
    } finally {
      setPending(false);
    }
  }

  return (
    <form onSubmit={handleSubmit} className="grid gap-3 text-sm">
      <label htmlFor={inputId} className="font-medium text-foreground">
        {t("account.displayName")}
      </label>
      <div className="flex flex-col gap-2 sm:flex-row">
        <input
          id={inputId}
          type="text"
          name="display_name"
          required
          maxLength={DISPLAY_NAME_MAX_LENGTH}
          autoComplete="nickname"
          aria-describedby={helpId}
          value={value}
          onChange={(e) => {
            setValue(e.target.value);
            setSaved(false);
          }}
          className={filterInputClassName}
        />
        <Button
          type="submit"
          disabled={pending}
          className="min-h-[44px] w-full sm:w-auto"
        >
          {pending
            ? t("account.displayNameSaving")
            : t("account.displayNameSave")}
        </Button>
      </div>
      <p id={helpId} className="text-xs text-muted-foreground">
        {t("account.displayNameHelp")}
      </p>
      {error ? <FormError>{error}</FormError> : null}
      <FormSuccess>{saved ? t("account.displayNameSaved") : null}</FormSuccess>
    </form>
  );
}
