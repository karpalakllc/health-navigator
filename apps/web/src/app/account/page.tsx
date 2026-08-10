import { redirect } from "next/navigation";
import { AccountPageHero } from "@/components/account/account-page-hero";
import { AccountProfilePhoto } from "@/components/account/account-profile-photo";
import { LogoutButton } from "@/components/auth/logout-button";
import { AccountLayout } from "@/components/account/account-layout";
import { ProfileContentCard } from "@/components/design/profile-content-card";
import { ForumHubIcon, ReviewsHubIcon } from "@/components/account/account-hub-icons";
import { HubLinkCard } from "@/components/ui/hub-link-card";
import { PageShell } from "@/components/ui/page-shell";
import { PageHeroBleed } from "@/components/design/page-hero-bleed";
import { getSessionToken } from "@/lib/auth/session";
import { fetchMe } from "@/lib/api/me";
import { ApiRequestError } from "@/lib/api/server";
import { accountRoleLabel } from "@/lib/roles";
import { t } from "@/i18n/t";
import { pageMetadata } from "@/lib/metadata";

export const metadata = pageMetadata(t("nav.account"), undefined, { noIndex: true });

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
    <>
      <PageHeroBleed>
        <AccountPageHero
          badge={t("account.hubBadge")}
          title={t("auth.accountTitle")}
          description={t("account.hubHeroDescription")}
        />
      </PageHeroBleed>

      <PageShell className="pb-16">
        <AccountLayout current="overview">
          <ProfileContentCard title={t("account.profilePhoto")}>
            <AccountProfilePhoto user={user} />
          </ProfileContentCard>
          <ProfileContentCard title={t("auth.accountTitle")}>
            <dl className="grid gap-4 text-sm">
              <div className="flex flex-col gap-1 sm:flex-row sm:justify-between sm:gap-4">
                <dt className="text-muted-foreground">{t("account.name")}</dt>
                <dd className="font-semibold text-foreground">{user.name}</dd>
              </div>
              <div className="flex flex-col gap-1 sm:flex-row sm:justify-between sm:gap-4">
                <dt className="text-muted-foreground">{t("account.email")}</dt>
                <dd className="break-all font-semibold text-foreground">{user.email}</dd>
              </div>
              <div className="flex flex-col gap-1 sm:flex-row sm:justify-between sm:gap-4">
                <dt className="text-muted-foreground">{t("account.role")}</dt>
                <dd className="font-semibold text-foreground">{accountRoleLabel(user)}</dd>
              </div>
            </dl>
          </ProfileContentCard>
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
    </>
  );
}
