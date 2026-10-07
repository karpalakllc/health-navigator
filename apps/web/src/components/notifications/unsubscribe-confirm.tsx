"use client";

import { useState } from "react";
import { Button } from "@/components/ui/button";
import { FormError, FormSuccess } from "@/components/ui/form-message";
import { t, tFormat } from "@/i18n/t";

/**
 * One click turns the e-mail type off. The page itself (a GET, which mail
 * scanners prefetch) changes nothing; only this button does.
 */
export function UnsubscribeConfirm({
  token,
  typeLabel,
  isReminder,
}: {
  token: string;
  typeLabel: string;
  isReminder: boolean;
}) {
  const [state, setState] = useState<"idle" | "pending" | "done" | "error">(
    "idle",
  );

  async function confirm() {
    setState("pending");

    try {
      const response = await fetch("/api/notifications/unsubscribe", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ token }),
      });

      setState(response.ok ? "done" : "error");
    } catch {
      setState("error");
    }
  }

  return (
    <div className="flex flex-col gap-4">
      <FormSuccess>
        {state === "done"
          ? isReminder
            ? t("notifications.unsubscribe.doneReminder")
            : tFormat("notifications.unsubscribe.done", { type: typeLabel })
          : null}
      </FormSuccess>
      {state === "error" ? (
        <FormError>{t("notifications.unsubscribe.error")}</FormError>
      ) : null}
      {state !== "done" ? (
        <Button
          type="button"
          size="lg"
          loading={state === "pending"}
          disabled={state === "pending"}
          onClick={() => void confirm()}
          className="self-stretch sm:self-start"
        >
          {isReminder
            ? t("notifications.unsubscribe.confirmReminder")
            : t("notifications.unsubscribe.confirm")}
        </Button>
      ) : null}
    </div>
  );
}
