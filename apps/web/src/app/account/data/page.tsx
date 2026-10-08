import { redirect } from "next/navigation";
import { AccountDelete } from "@/components/account/account-delete";
import { AccountExport } from "@/components/account/account-export";
import {
  AccountLayout,
  AccountPage,
} from "@/components/account/account-layout";
import { AccountPageHero } from "@/components/account/account-page-hero";
import { Card } from "@/components/ui/card";
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

const DELETE_CONSEQUENCES: MessageKey[] = [
  "account.data.deleteListPersonal",
  "account.data.deleteListContent",
  "account.data.deleteListSessions",
  "account.data.deleteListEmail",
];

export default async function AccountDataPage() {
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
            <AccountExport />
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
