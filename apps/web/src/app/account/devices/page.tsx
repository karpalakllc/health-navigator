import { redirect } from "next/navigation";
import { AccountDevices } from "@/components/account/account-devices";
import {
  AccountLayout,
  AccountPage,
} from "@/components/account/account-layout";
import { AccountPageHero } from "@/components/account/account-page-hero";
import { fetchMyDevices } from "@/lib/api/account";
import { ApiRequestError } from "@/lib/api/server";
import { getSessionToken } from "@/lib/auth/session";
import { pageMetadata } from "@/lib/metadata";
import { t } from "@/i18n/t";

export const metadata = pageMetadata(t("account.navDevices"), undefined, {
  noIndex: true,
});

const LOGIN = "/login?redirect=/account/devices";

export default async function AccountDevicesPage() {
  if (!(await getSessionToken())) {
    redirect(LOGIN);
  }

  let devices;

  try {
    devices = await fetchMyDevices();
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
        title={t("account.navDevices")}
        description={t("account.devices.heroDescription")}
      />
      <AccountLayout current="devices">
        {/* Each device is its own card; the section itself stays plain. */}
        <section aria-labelledby="account-devices">
          <AccountDevices devices={devices} />
        </section>
      </AccountLayout>
    </AccountPage>
  );
}
