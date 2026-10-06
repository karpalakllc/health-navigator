import { redirect } from "next/navigation";
import { AccountDelete } from "@/components/account/account-delete";
import {
  AccountLayout,
  AccountPage,
} from "@/components/account/account-layout";
import { AccountPageHero } from "@/components/account/account-page-hero";
import { buttonClassName } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import { FormError } from "@/components/ui/form-message";
import { Icon } from "@/components/ui/icons";
import { SectionHeader } from "@/components/ui/section-header";
import { fetchMe } from "@/lib/api/me";
import { ApiRequestError } from "@/lib/api/server";
import { getSessionToken } from "@/lib/auth/session";
import { pageMetadata } from "@/lib/metadata";
import { t, type MessageKey } from "@/i18n/t";

export const metadata = pageMetadata(t("account.navData"), undefined, {
  noIndex: true,
});

const LOGIN = "/login?redirect=/account/data";

/** Set by /api/account/export when it sends the browser back here. */
const EXPORT_ERRORS: Record<string, MessageKey> = {
  throttled: "account.data.exportThrottled",
  error: "account.data.exportError",
};

const DELETE_CONSEQUENCES: MessageKey[] = [
  "account.data.deleteListPersonal",
  "account.data.deleteListContent",
  "account.data.deleteListSessions",
  "account.data.deleteListEmail",
];

export default async function AccountDataPage({
  searchParams,
}: {
  searchParams: Promise<{ export?: string }>;
}) {
  if (!(await getSessionToken())) {
    redirect(LOGIN);
  }

  try {
    // Confirms the session is still valid before offering anything.
    await fetchMe();
  } catch (error) {
    if (error instanceof ApiRequestError && error.status === 401) {
      redirect(LOGIN);
    }

    throw error;
  }

  const exportStatus = (await searchParams).export;
  const exportError = exportStatus ? EXPORT_ERRORS[exportStatus] : undefined;

  return (
    <AccountPage>
      <AccountPageHero
        badge={t("account.hubBadge")}
        title={t("account.navData")}
        description={t("account.data.heroDescription")}
      />
      <AccountLayout current="data">
        <Card as="section" aria-labelledby="account-export">
          <div className="flex flex-col gap-5">
            <SectionHeader
              id="account-export"
              title={t("account.data.exportHeading")}
              level={2}
            />
            <p className="type-body text-ink-2 measure">
              {t("account.data.exportBody")}
            </p>
            {exportError ? <FormError>{t(exportError)}</FormError> : null}
            {/* A plain link, not next/link: this is a file download from a
                route handler, not a page, and must work without JavaScript. */}
            <a
              href="/api/account/export"
              className={buttonClassName({
                variant: "primary",
                className: "w-full sm:w-auto sm:self-start",
              })}
            >
              <Icon name="file-text" size={20} />
              <span>{t("account.data.exportAction")}</span>
            </a>
          </div>
        </Card>

        <Card as="section" aria-labelledby="account-delete">
          <div className="flex flex-col gap-5">
            <SectionHeader
              id="account-delete"
              title={t("account.data.deleteHeading")}
              level={2}
            />
            <div className="flex flex-col gap-3">
              <p className="type-body font-semibold text-ink">
                {t("account.data.deleteIntro")}
              </p>
              <ul className="flex flex-col gap-2 type-body text-ink-2 measure">
                {DELETE_CONSEQUENCES.map((key) => (
                  <li key={key} className="flex gap-3">
                    <Icon
                      name="info"
                      size={20}
                      className="mt-1 shrink-0 text-ink"
                    />
                    <span>{t(key)}</span>
                  </li>
                ))}
              </ul>
              <p className="type-body text-ink-2 measure">
                {t("account.data.deleteExportFirst")}
              </p>
            </div>
            <AccountDelete />
          </div>
        </Card>
      </AccountLayout>
    </AccountPage>
  );
}
