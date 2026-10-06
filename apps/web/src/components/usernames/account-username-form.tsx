"use client";

import { useRouter } from "next/navigation";
import { useCallback, useState } from "react";
import { Button } from "@/components/ui/button";
import { FormError, FormSuccess } from "@/components/ui/form-message";
import { Notice } from "@/components/ui/notice";
import {
  UsernameField,
  type UsernameAvailability,
} from "@/components/usernames/username-field";
import { formatMkDate } from "@/lib/mk-date";
import {
  isTemporaryUsername,
  normalizeUsername,
  usernameFormatError,
} from "@/lib/username";
import { t, tFormat } from "@/i18n/t";

type AccountUsernameFormProps = {
  username: string;
  /** Still a temporary „clen-…“ name: the first choice is not limited. */
  mustChoose: boolean;
  /** ISO time from which the next change is allowed, or null for now. */
  changeAvailableAt: string | null;
  /** The chooser: different button text, and where to go once saved. */
  variant?: "settings" | "chooser";
  onSaved?: (username: string) => void;
};

/**
 * Choose or change the public username (PATCH /me/profile through the web
 * tier). Once every 90 days; until then the field is read-only and says when
 * the next change is possible. The API repeats every check.
 */
export function AccountUsernameForm({
  username,
  mustChoose,
  changeAvailableAt,
  variant = "settings",
  onSaved,
}: AccountUsernameFormProps) {
  const router = useRouter();
  const current = mustChoose && isTemporaryUsername(username) ? "" : username;
  const [value, setValue] = useState(current);
  const [saved, setSaved] = useState<string | null>(null);
  const [nextChange, setNextChange] = useState(changeAvailableAt);
  const [fieldError, setFieldError] = useState<string | undefined>();
  const [error, setError] = useState<string | null>(null);
  const [pending, setPending] = useState(false);
  const [availability, setAvailability] = useState<UsernameAvailability>({
    state: "idle",
  });
  const onAvailability = useCallback(
    (next: UsernameAvailability) => setAvailability(next),
    [],
  );

  const locked = !mustChoose && nextChange !== null;
  const nextDate = locked ? formatMkDate(nextChange) : null;

  async function handleSubmit(event: React.FormEvent) {
    event.preventDefault();
    setError(null);
    setSaved(null);

    const username = normalizeUsername(value);
    const problem = usernameFormatError(username);
    const unavailable =
      availability.state === "unavailable" && availability.username === username
        ? availability.message
        : null;

    if (problem || unavailable) {
      setFieldError(problem ? t(problem) : (unavailable ?? undefined));
      return;
    }

    setFieldError(undefined);
    setPending(true);

    try {
      const response = await fetch("/api/session/profile", {
        method: "PATCH",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ username }),
      });
      const payload = (await response.json().catch(() => null)) as {
        message?: string;
        errors?: { username?: string[] };
        data?: {
          user?: {
            username?: string;
            username_change_available_at?: string | null;
          };
        };
      } | null;

      if (!response.ok) {
        const message = payload?.errors?.username?.[0];

        if (message) {
          setFieldError(message);
        } else {
          setError(payload?.message ?? t("usernames.saveError"));
        }

        return;
      }

      const stored = payload?.data?.user?.username ?? username;
      setValue(stored);
      setNextChange(payload?.data?.user?.username_change_available_at ?? null);
      setSaved(stored);
      onSaved?.(stored);
      router.refresh();
    } catch {
      setError(t("usernames.saveError"));
    } finally {
      setPending(false);
    }
  }

  return (
    <form
      noValidate
      onSubmit={handleSubmit}
      className="flex max-w-xl flex-col gap-4"
    >
      {mustChoose && variant === "settings" ? (
        <Notice tone="info">{t("usernames.accountNotice")}</Notice>
      ) : null}
      {locked ? (
        <div className="flex flex-col gap-2">
          <p className="type-meta text-ink-2">{t("usernames.label")}</p>
          <p className="type-body font-semibold break-all text-ink">{value}</p>
          <p className="type-meta text-ink-2">
            {t("usernames.settingsHelp")}{" "}
            {nextDate
              ? tFormat("usernames.nextChange", { date: nextDate })
              : null}
          </p>
        </div>
      ) : (
        <UsernameField
          id={variant === "chooser" ? "chooser-username" : "account-username"}
          hint={t(
            variant === "chooser"
              ? "usernames.registerHelp"
              : "usernames.settingsHelp",
          )}
          error={fieldError}
          value={value}
          current={current || undefined}
          onChange={(next) => {
            setValue(next);
            setSaved(null);
            setFieldError(undefined);
          }}
          onAvailability={onAvailability}
        />
      )}
      {error ? <FormError>{error}</FormError> : null}
      <FormSuccess>{saved ? t("usernames.saved") : null}</FormSuccess>
      {locked ? null : (
        <Button
          type="submit"
          loading={pending}
          disabled={pending}
          className="w-full sm:w-auto sm:self-start"
        >
          {pending
            ? t("usernames.saving")
            : t(
                variant === "chooser"
                  ? "usernames.chooserSubmit"
                  : "usernames.save",
              )}
        </Button>
      )}
    </form>
  );
}
