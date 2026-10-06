"use client";

import { useEffect, useState } from "react";
import { TextField } from "@/components/auth/text-field";
import { Icon } from "@/components/ui/icons";
import {
  normalizeUsername,
  USERNAME_MAX_LENGTH,
  usernameFormatError,
} from "@/lib/username";
import { t } from "@/i18n/t";

/** Wait this long after the last keystroke before asking the API. */
export const AVAILABILITY_DELAY_MS = 450;

export type UsernameAvailability =
  | { state: "idle" }
  | { state: "checking"; username: string }
  | { state: "available"; username: string }
  | { state: "unavailable"; username: string; message: string };

type UsernameFieldProps = {
  id: string;
  value: string;
  onChange: (value: string) => void;
  /** A submit-time error (format or the API's 422), shown under the field. */
  error?: string;
  hint: string;
  /** The member's current name: never checked, it is theirs. */
  current?: string;
  onAvailability?: (availability: UsernameAvailability) => void;
};

/**
 * The username input with a quiet, live availability answer under it. Only
 * names that pass the format rules are sent, and only once typing pauses;
 * the answer is announced politely and says no more than the API does
 * (free, or the same message registration would give).
 */
export function UsernameField({
  id,
  value,
  onChange,
  error,
  hint,
  current,
  onAvailability,
}: UsernameFieldProps) {
  const [availability, setAvailability] = useState<UsernameAvailability>({
    state: "idle",
  });
  const username = normalizeUsername(value);
  const checkable =
    username !== "" &&
    username !== current &&
    usernameFormatError(username) === null;

  useEffect(() => {
    onAvailability?.(availability);
  }, [availability, onAvailability]);

  useEffect(() => {
    if (!checkable) {
      return;
    }

    const controller = new AbortController();
    const timer = window.setTimeout(async () => {
      setAvailability({ state: "checking", username });

      try {
        const response = await fetch(
          `/api/usernames/availability?username=${encodeURIComponent(username)}`,
          {
            signal: controller.signal,
            headers: { Accept: "application/json" },
          },
        );
        const payload = (await response.json().catch(() => null)) as {
          data?: { available?: boolean; message?: string | null };
        } | null;

        if (!response.ok || typeof payload?.data?.available !== "boolean") {
          // Rate limit or outage: say nothing; the submit will decide.
          setAvailability({ state: "idle" });
          return;
        }

        setAvailability(
          payload.data.available
            ? { state: "available", username }
            : {
                state: "unavailable",
                username,
                message: payload.data.message ?? t("usernames.notAllowed"),
              },
        );
      } catch {
        if (!controller.signal.aborted) {
          setAvailability({ state: "idle" });
        }
      }
    }, AVAILABILITY_DELAY_MS);

    return () => {
      window.clearTimeout(timer);
      controller.abort();
    };
  }, [checkable, username]);

  // An answer about an earlier spelling is not an answer about this one.
  const shown =
    checkable &&
    "username" in availability &&
    availability.username === username
      ? availability
      : null;
  const statusId = `${id}-availability`;

  return (
    <div className="flex flex-col gap-2">
      <TextField
        id={id}
        label={t("usernames.label")}
        hint={hint}
        error={error}
        type="text"
        name="username"
        required
        // Login is by e-mail: "username" would make password managers save
        // this public handle as the login name.
        autoComplete="nickname"
        autoCapitalize="none"
        spellCheck={false}
        maxLength={USERNAME_MAX_LENGTH}
        value={value}
        aria-invalid={shown?.state === "unavailable" ? true : undefined}
        aria-describedby={statusId}
        onChange={(event) => onChange(event.target.value)}
      />
      <p
        id={statusId}
        role="status"
        className="flex min-h-6 items-center gap-2 type-meta text-ink-2"
      >
        {shown?.state === "checking" ? t("usernames.checking") : null}
        {shown?.state === "available" ? (
          <>
            <Icon name="check" size={18} className="text-care" />
            {t("usernames.available")}
          </>
        ) : null}
        {shown?.state === "unavailable" && !error ? (
          <>
            <Icon name="info" size={18} className="text-ink" />
            <span className="text-ink">{shown.message}</span>
          </>
        ) : null}
      </p>
    </div>
  );
}
