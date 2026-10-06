import Link from "next/link";
import { redirect } from "next/navigation";
import {
  AccountLayout,
  AccountPage,
} from "@/components/account/account-layout";
import { AccountPageHero } from "@/components/account/account-page-hero";
import { AccountProfilePhoto } from "@/components/account/account-profile-photo";
import { LogoutButton } from "@/components/auth/logout-button";
import { Card } from "@/components/ui/card";
import { Icon, type IconName } from "@/components/ui/icons";
import { SectionHeader } from "@/components/ui/section-header";
import { Tag } from "@/components/ui/tag";
import { AccountUsernameForm } from "@/components/usernames/account-username-form";
import { getSessionToken } from "@/lib/auth/session";
import { fetchMe } from "@/lib/api/me";
import { ApiRequestError } from "@/lib/api/server";
import { accountRoleLabel } from "@/lib/roles";
import { t } from "@/i18n/t";
import { pageMetadata } from "@/lib/metadata";

export const metadata = pageMetadata(t("nav.account"), undefined, {
  noIndex: true,
});

export default async function AccountOverviewPage() {
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
    <AccountPage>
      <AccountPageHero
        badge={t("account.hubBadge")}
        title={t("auth.accountTitle")}
        description={t("account.hubHeroDescription")}
      />
      <AccountLayout current="overview">
        <Card as="section" aria-labelledby="account-profile">
          <div className="flex flex-col gap-6">
            <SectionHeader
              id="account-profile"
              title={t("account.sectionProfile")}
              level={2}
              description={t("account.sectionProfileHint")}
            />
            <dl className="flex flex-col gap-4">
              <ProfileRow label={t("account.name")}>{user.name}</ProfileRow>
              <ProfileRow label={t("account.email")}>
                <span className="break-all">{user.email}</span>
              </ProfileRow>
              <ProfileRow label={t("account.role")}>
                <Tag tone="sand">{accountRoleLabel(user)}</Tag>
              </ProfileRow>
            </dl>
            <div className="border-t border-line pt-6">
              <AccountUsernameForm
                username={user.username}
                mustChoose={user.must_choose_username}
                changeAvailableAt={user.username_change_available_at}
              />
            </div>
          </div>
        </Card>

        <Card as="section" aria-labelledby="account-photo">
          <div className="flex flex-col gap-5">
            <SectionHeader
              id="account-photo"
              title={t("account.profilePhoto")}
              level={2}
            />
            <AccountProfilePhoto user={user} />
          </div>
        </Card>

        <section
          aria-labelledby="account-activity"
          className="flex flex-col gap-4"
        >
          <SectionHeader
            id="account-activity"
            title={t("account.sectionActivity")}
            level={2}
          />
          <ul className="grid gap-4 sm:grid-cols-2">
            <ActivityLink
              href="/account/reviews"
              icon="star"
              title={t("nav.myReviews")}
              description={t("account.hubReviewsDesc")}
            />
            <ActivityLink
              href="/account/forum"
              icon="message-circle"
              title={t("nav.myForum")}
              description={t("account.hubForumDesc")}
            />
          </ul>
        </section>

        <div className="flex flex-col gap-3 border-t border-line pt-6 sm:flex-row sm:items-center sm:justify-between">
          <p className="type-body text-ink-2">{t("account.signOutHint")}</p>
          <LogoutButton withIcon className="w-full sm:w-auto" />
        </div>
      </AccountLayout>
    </AccountPage>
  );
}

function ProfileRow({
  label,
  children,
}: {
  label: string;
  children: React.ReactNode;
}) {
  return (
    <div className="flex flex-col gap-1 sm:grid sm:grid-cols-[10rem_minmax(0,1fr)] sm:items-center sm:gap-6">
      <dt className="type-meta text-ink-2">{label}</dt>
      <dd className="type-body font-semibold text-ink">{children}</dd>
    </div>
  );
}

function ActivityLink({
  href,
  icon,
  title,
  description,
}: {
  href: string;
  icon: IconName;
  title: string;
  description: string;
}) {
  return (
    <li>
      <Link
        href={href}
        className="card hover-lift flex h-full min-h-14 items-center gap-4 p-5"
      >
        <span className="inline-flex size-12 shrink-0 items-center justify-center rounded-full bg-apricot text-ink">
          <Icon name={icon} size={24} />
        </span>
        <span className="flex min-w-0 flex-1 flex-col gap-1">
          <span className="type-h3 text-ink">{title}</span>
          <span className="type-meta text-ink-2">{description}</span>
        </span>
        <Icon name="chevron-right" size={24} className="text-ink" />
      </Link>
    </li>
  );
}
