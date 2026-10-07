"use client";

import { useState } from "react";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import { Checkbox } from "@/components/ui/field";
import { FormError, FormSuccess } from "@/components/ui/form-message";
import { Icon } from "@/components/ui/icons";
import {
  PREFERENCE_TYPES,
  type NotificationPreferences,
  type PreferenceType,
} from "@/lib/api/notifications";
import { t } from "@/i18n/t";

const LABELS: Record<
  PreferenceType,
  { label: Parameters<typeof t>[0]; hint: Parameters<typeof t>[0] }
> = {
  moderation: {
    label: "notifications.types.moderation",
    hint: "notifications.types.moderationHint",
  },
  review_reply: {
    label: "notifications.types.review_reply",
    hint: "notifications.types.review_replyHint",
  },
  review_helpful: {
    label: "notifications.types.review_helpful",
    hint: "notifications.types.review_helpfulHint",
  },
  impact_digest: {
    label: "notifications.types.impact_digest",
    hint: "notifications.types.impact_digestHint",
  },
};

type Patch = {
  email_enabled?: boolean;
  types?: Partial<Record<PreferenceType, boolean>>;
};

/**
 * The member's e-mail switches. Each change saves at once (no separate save
 * button to forget) and is confirmed in a polite live region; a failed save
 * puts the switch back. With e-mail off, the per-type switches stay visible
 * but disabled, so turning it back on restores the member's earlier choice.
 */
export function NotificationPreferencesForm({
  initial,
  showInvite = false,
}: {
  initial: NotificationPreferences;
  /** The one-time digest invitation (after the first published review). */
  showInvite?: boolean;
}) {
  const [inviteOpen, setInviteOpen] = useState(showInvite);
  const [prefs, setPrefs] = useState(initial);
  const [pending, setPending] = useState(false);
  const [message, setMessage] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);

  async function save(patch: Patch) {
    const previous = prefs;
    setPrefs({
      ...prefs,
      email_enabled: patch.email_enabled ?? prefs.email_enabled,
      types: { ...prefs.types, ...patch.types },
    });
    setPending(true);
    setMessage(null);
    setError(null);

    try {
      const response = await fetch("/api/notifications/preferences", {
        method: "PUT",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(patch),
      });
      const payload = (await response.json().catch(() => null)) as {
        data?: NotificationPreferences;
      } | null;

      if (!response.ok || !payload?.data) {
        setPrefs(previous);
        setError(t("notifications.saveError"));
        return;
      }

      setPrefs(payload.data);
      setMessage(
        patch.types?.impact_digest === true && inviteOpen
          ? t("notifications.digestInviteAccepted")
          : t("notifications.saved"),
      );

      if (payload.data.types.impact_digest) {
        setInviteOpen(false);
      }
    } catch {
      setPrefs(previous);
      setError(t("notifications.saveError"));
    } finally {
      setPending(false);
    }
  }

  return (
    <section
      id="notification-preferences"
      aria-labelledby="notification-preferences-title"
      className="flex scroll-mt-28 flex-col gap-3"
    >
      <div className="flex flex-col gap-1">
        <h2 id="notification-preferences-title" className="type-h2 text-ink">
          {t("notifications.prefsTitle")}
        </h2>
        <p className="type-meta text-ink-2">{t("notifications.prefsLead")}</p>
      </div>
      {inviteOpen && prefs.email_enabled && !prefs.types.impact_digest ? (
        <DigestInvite
          pending={pending}
          onAccept={() => void save({ types: { impact_digest: true } })}
        />
      ) : null}
      <Card padding="md" className="flex flex-col gap-1">
        <Checkbox
          label={
            <span className="font-semibold">{t("notifications.emailAll")}</span>
          }
          hint={t("notifications.emailAllHint")}
          checked={prefs.email_enabled}
          disabled={pending}
          onChange={(event) =>
            void save({ email_enabled: event.target.checked })
          }
        />
        <fieldset className="m-0 flex flex-col gap-1 border-0 border-t border-line p-0 pt-2">
          <legend className="sr-only">{t("notifications.prefsTitle")}</legend>
          {PREFERENCE_TYPES.map((type) => (
            <Checkbox
              key={type}
              label={t(LABELS[type].label)}
              hint={t(LABELS[type].hint)}
              checked={prefs.types[type]}
              disabled={pending || !prefs.email_enabled}
              onChange={(event) =>
                void save({ types: { [type]: event.target.checked } })
              }
            />
          ))}
        </fieldset>
      </Card>
      <FormSuccess>{message}</FormSuccess>
      {error ? <FormError>{error}</FormError> : null}
    </section>
  );
}

/**
 * The one-time invitation to the monthly digest (after the first published
 * review). Turning it on is the member's choice; it goes away once the
 * digest is on.
 */
function DigestInvite({
  pending,
  onAccept,
}: {
  pending: boolean;
  onAccept: () => void;
}) {
  return (
    <Card
      as="aside"
      tone="apricot"
      padding="md"
      aria-labelledby="digest-invite-title"
      className="flex flex-col gap-3"
    >
      <div className="flex items-start gap-3">
        <span className="inline-flex size-10 shrink-0 items-center justify-center rounded-full bg-white text-ink">
          <Icon name="mail" size={20} />
        </span>
        <div className="flex flex-col gap-1">
          <h3
            id="digest-invite-title"
            className="type-body font-semibold text-ink"
          >
            {t("notifications.digestInviteTitle")}
          </h3>
          <p className="type-meta text-ink">
            {t("notifications.digestInviteBody")}
          </p>
        </div>
      </div>
      <div className="sm:pl-[52px]">
        <Button
          type="button"
          loading={pending}
          disabled={pending}
          onClick={onAccept}
        >
          {t("notifications.digestInviteAccept")}
        </Button>
      </div>
    </Card>
  );
}
