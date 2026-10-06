"use client";

import { useRouter } from "next/navigation";
import { useRef, useState } from "react";
import { Button } from "@/components/ui/button";
import { FormError, FormSuccess } from "@/components/ui/form-message";
import { Icon } from "@/components/ui/icons";
import { Tag } from "@/components/ui/tag";
import type { AccountDevice } from "@/lib/api/account";
import { UNKNOWN_DEVICE_LABEL } from "@/lib/device-label";
import { formatMkDate } from "@/lib/mk-date";
import { t, tFormat } from "@/i18n/t";

/** Labels stored before devices were named, or by clients that send none. */
const UNNAMED = new Set([UNKNOWN_DEVICE_LABEL, "api", ""]);

export function deviceName(device: Pick<AccountDevice, "name">): string {
  return UNNAMED.has(device.name.trim().toLowerCase())
    ? t("account.devices.unknownDevice")
    : device.name;
}

/**
 * The member's signed-in devices, each with a sign-out button, plus „sign out
 * everywhere else“. The current device is labelled and cannot be signed out
 * here (that is the ordinary „Одјава“), so nobody locks themselves out by
 * accident.
 *
 * After a sign-out the row disappears, the result is announced in a polite
 * live region, and focus moves to the list heading so keyboard users are not
 * left on a button that no longer exists.
 */
export function AccountDevices({ devices }: { devices: AccountDevice[] }) {
  const router = useRouter();
  const [list, setList] = useState(devices);
  const [pending, setPending] = useState<number | "others" | null>(null);
  const [message, setMessage] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const headingRef = useRef<HTMLHeadingElement>(null);

  const others = list.filter((device) => !device.is_current);

  async function revoke(target: AccountDevice | "others") {
    setError(null);
    setMessage(null);
    setPending(target === "others" ? "others" : target.id);

    try {
      const response = await fetch(
        target === "others"
          ? "/api/account/devices"
          : `/api/account/devices/${target.id}`,
        { method: "DELETE" },
      );

      if (response.status === 401) {
        router.push("/login?redirect=/account/devices");
        return;
      }

      // Already gone (signed out elsewhere, or expired): the outcome is the same.
      if (!response.ok && !(target !== "others" && response.status === 404)) {
        const payload = (await response.json().catch(() => null)) as {
          message?: string;
        } | null;
        setError(payload?.message ?? t("account.devices.revokeError"));
        return;
      }

      if (target === "others") {
        setList((current) => current.filter((device) => device.is_current));
        setMessage(t("account.devices.revokedOthers"));
      } else {
        setList((current) =>
          current.filter((device) => device.id !== target.id),
        );
        setMessage(
          tFormat("account.devices.revoked", { name: deviceName(target) }),
        );
      }

      headingRef.current?.focus();
      router.refresh();
    } catch {
      setError(t("account.devices.revokeError"));
    } finally {
      setPending(null);
    }
  }

  return (
    <div className="flex flex-col gap-5">
      <div className="flex flex-col gap-1">
        <h2
          ref={headingRef}
          id="account-devices"
          tabIndex={-1}
          className="type-h2 text-ink"
        >
          {t("account.devices.heading")}
        </h2>
        <p className="type-meta text-ink-2">{t("account.devices.hint")}</p>
      </div>

      <FormSuccess>{message}</FormSuccess>
      {error ? <FormError>{error}</FormError> : null}

      <ul aria-labelledby="account-devices" className="flex flex-col gap-3">
        {list.map((device) => {
          const name = deviceName(device);
          const signedIn = formatMkDate(device.created_at);
          const lastUsed = formatMkDate(device.last_used_at);
          // Two „Safari · iOS“ rows need two different button names.
          const sameName =
            list.filter((other) => deviceName(other) === name).length > 1;
          const revokeLabel =
            sameName && signedIn
              ? tFormat("account.devices.revokeAriaDated", {
                  name,
                  date: signedIn,
                })
              : tFormat("account.devices.revokeAria", { name });

          return (
            <li
              key={device.id}
              className="card flex flex-col gap-4 p-5 sm:flex-row sm:items-center"
            >
              <div className="flex min-w-0 flex-1 items-start gap-4">
                <span className="inline-flex size-12 shrink-0 items-center justify-center rounded-full bg-apricot text-ink">
                  <Icon name="lock" size={24} />
                </span>
                <div className="flex min-w-0 flex-1 flex-col gap-1">
                  <p className="flex flex-wrap items-center gap-2">
                    <span className="type-h3 break-words text-ink">{name}</span>
                    {device.is_current ? (
                      <Tag tone="care" icon="check">
                        {t("account.devices.current")}
                      </Tag>
                    ) : null}
                  </p>
                  {signedIn ? (
                    <p className="type-meta text-ink-2">
                      {tFormat("account.devices.signedIn", { date: signedIn })}
                    </p>
                  ) : null}
                  {lastUsed ? (
                    <p className="type-meta text-ink-2">
                      {tFormat("account.devices.lastUsed", { date: lastUsed })}
                    </p>
                  ) : null}
                  {device.is_current ? (
                    <p className="type-meta text-ink-2">
                      {t("account.devices.thisDeviceHint")}
                    </p>
                  ) : null}
                </div>
              </div>
              {device.is_current ? null : (
                <Button
                  variant="secondary"
                  leadingIcon="log-out"
                  loading={pending === device.id}
                  disabled={pending !== null}
                  aria-label={revokeLabel}
                  onClick={() => revoke(device)}
                  className="w-full sm:w-auto"
                >
                  {t("account.devices.revoke")}
                </Button>
              )}
            </li>
          );
        })}
      </ul>

      {others.length > 0 ? (
        <Button
          variant="secondary"
          leadingIcon="log-out"
          loading={pending === "others"}
          disabled={pending !== null}
          onClick={() => revoke("others")}
          className="w-full sm:w-auto sm:self-start"
        >
          {t("account.devices.revokeOthers")}
        </Button>
      ) : (
        <p className="type-body text-ink-2">{t("account.devices.onlyThis")}</p>
      )}
    </div>
  );
}
