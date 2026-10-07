import Link from "next/link";
import { UnsubscribeConfirm } from "@/components/notifications/unsubscribe-confirm";
import { Card } from "@/components/ui/card";
import { Icon } from "@/components/ui/icons";
import { Notice } from "@/components/ui/notice";
import { apiFetch } from "@/lib/api/server";
import { pageMetadata } from "@/lib/metadata";
import { t, type MessageKey } from "@/i18n/t";

export const metadata = pageMetadata(
  t("notifications.unsubscribe.title"),
  t("notifications.unsubscribe.description"),
  { noIndex: true },
);

const TOKEN = /^\d{1,18}\.[a-z_]{1,40}\.[A-Za-z0-9_-]{43}$/;

const TYPE_LABELS: Record<string, MessageKey> = {
  moderation: "notifications.types.moderation",
  review_reply: "notifications.types.review_reply",
  review_helpful: "notifications.types.review_helpful",
  impact_digest: "notifications.types.impact_digest",
  review_reminder: "notifications.unsubscribe.review_reminder",
};

/** Which e-mail type a valid link names; null for anything else. */
async function linkType(token: string | undefined): Promise<string | null> {
  if (!token || !TOKEN.test(token)) {
    return null;
  }

  try {
    const response = await apiFetch(
      `/notifications/unsubscribe?token=${encodeURIComponent(token)}`,
    );

    if (!response.ok) {
      return null;
    }

    const payload = (await response.json()) as { data?: { type?: string } };
    const type = payload.data?.type;

    return type && type in TYPE_LABELS ? type : null;
  } catch {
    return null;
  }
}

/**
 * The signed unsubscribe link from a member e-mail (G4). Works without
 * signing in; the button, not the visit, turns the e-mails off.
 */
export default async function UnsubscribePage({
  searchParams,
}: {
  searchParams: Promise<{ token?: string | string[] }>;
}) {
  const raw = (await searchParams).token;
  const token = typeof raw === "string" ? raw : undefined;
  const type = await linkType(token);

  return (
    <div className="mx-auto flex w-full max-w-[720px] flex-col gap-6 px-5 pb-14 pt-8 lg:pt-14">
      <h1 className="type-h1 text-ink">
        {t("notifications.unsubscribe.title")}
      </h1>
      {type && token ? (
        <Card className="flex flex-col gap-4">
          <div className="flex items-start gap-3">
            <span className="inline-flex size-12 shrink-0 items-center justify-center rounded-full bg-apricot text-ink">
              <Icon name="mail" size={24} />
            </span>
            <div className="flex flex-col gap-1">
              <p className="type-body font-semibold text-ink">
                {t("notifications.unsubscribe.question")}
              </p>
              <p className="type-body text-ink">{t(TYPE_LABELS[type])}</p>
            </div>
          </div>
          <UnsubscribeConfirm
            token={token}
            typeLabel={t(TYPE_LABELS[type])}
            isReminder={type === "review_reminder"}
          />
        </Card>
      ) : (
        <Notice tone="info">{t("notifications.unsubscribe.invalid")}</Notice>
      )}
      <Link
        href="/account/notifications"
        className="link-underline inline-flex min-h-12 items-center self-start type-body font-semibold text-ink"
      >
        {t("notifications.unsubscribe.manage")}
      </Link>
    </div>
  );
}
