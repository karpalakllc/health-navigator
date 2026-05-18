import { redirect } from "next/navigation";
import { AccountProfilePhoto } from "@/components/account/account-profile-photo";
import { LogoutButton } from "@/components/auth/logout-button";
import { AccountLayout } from "@/components/account/account-layout";
import { PageHeader } from "@/components/directory/page-header";
import { Card } from "@/components/ui/card";
import { ForumHubIcon, ReviewsHubIcon } from "@/components/account/account-hub-icons";
import { HubLinkCard } from "@/components/ui/hub-link-card";
import { PageShell } from "@/components/ui/page-shell";
import { getSessionToken } from "@/lib/auth/session";
import { fetchMe } from "@/lib/api/me";
import { ApiRequestError } from "@/lib/api/server";
import { accountRoleLabel } from "@/lib/roles";
import { t } from "@/i18n/t";

export default async function AccountPage() {
  const token = await getSessionToken();

  if (!token) {
    redirect("/login?redirect=/account");
  }

  let user;

  try {
    user = await fetchMe();
  } catch (error) {
    if (error instanceof ApiRequestError && error.status === 401) {
      redirect("/login?redirect=/account");
    }

    throw error;
  }

  return (
    <PageShell>
      <AccountLayout current="overview">
        <PageHeader title={t("auth.accountTitle")} description={t("auth.accountDescription")} />
        <Card className="p-5 sm:p-6">
          <AccountProfilePhoto user={user} />
        </Card>
        <Card className="p-5 sm:p-6">
          <dl className="grid gap-4 text-sm">
            <div className="flex flex-col gap-1 sm:flex-row sm:justify-between sm:gap-4">
              <dt className="text-muted-foreground">{t("account.name")}</dt>
              <dd className="font-medium text-foreground">{user.name}</dd>
            </div>
            <div className="flex flex-col gap-1 sm:flex-row sm:justify-between sm:gap-4">
              <dt className="text-muted-foreground">{t("account.email")}</dt>
              <dd className="break-all font-medium text-foreground">{user.email}</dd>
            </div>
            <div className="flex flex-col gap-1 sm:flex-row sm:justify-between sm:gap-4">
              <dt className="text-muted-foreground">{t("account.role")}</dt>
              <dd className="font-medium text-foreground">{accountRoleLabel(user)}</dd>
            </div>
          </dl>
        </Card>
        <div className="grid gap-4 sm:grid-cols-2">
          <HubLinkCard
            href="/account/reviews"
            title={t("nav.myReviews")}
            description={t("account.hubReviewsDesc")}
            icon={<ReviewsHubIcon />}
            iconClassName="bg-amber-500/15 text-amber-800 dark:text-amber-400"
          />
          <HubLinkCard
            href="/account/forum"
            title={t("nav.myForum")}
            description={t("account.hubForumDesc")}
            icon={<ForumHubIcon />}
            iconClassName="bg-primary/10 text-primary"
          />
        </div>
        <LogoutButton className="w-full sm:w-auto" />
      </AccountLayout>
    </PageShell>
  );
}
