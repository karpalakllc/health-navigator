import { redirect } from "next/navigation";
import { Breadcrumbs } from "@/components/directory/breadcrumbs";
import { forumPageClass } from "@/components/forum/forum-layout";
import { ForumNewTopicComposer } from "@/components/forum/forum-new-topic-composer";
import { IconButton } from "@/components/ui/button";
import { getShellSession } from "@/lib/auth/header-session";
import { getSessionToken } from "@/lib/auth/session";
import { fetchForumCategories } from "@/lib/api/forum";
import { fetchPublicSettings } from "@/lib/api/settings";
import { isModuleOn } from "@/lib/api/public-settings";
import { pageMetadata } from "@/lib/metadata";
import { t } from "@/i18n/t";
import type { Metadata } from "next";

export const metadata: Metadata = pageMetadata(
  t("forum.newTopicPageTitle"),
  t("forum.newTopicPageDescription"),
);

type NewTopicPageProps = {
  searchParams: Promise<{ category?: string }>;
};

export default async function NewTopicPage({
  searchParams,
}: NewTopicPageProps) {
  const settings = await fetchPublicSettings();

  // The forum hub explains that the module is off; there is nothing to compose.
  if (!isModuleOn(settings, "public_forum")) {
    redirect("/forum");
  }

  const token = await getSessionToken();

  if (!token) {
    const params = await searchParams;
    const category = params.category?.trim();
    const redirectTarget = category
      ? `/forum/new?category=${encodeURIComponent(category)}`
      : "/forum/new";
    redirect(`/login?redirect=${encodeURIComponent(redirectTarget)}`);
  }

  const params = await searchParams;
  const [categories, session] = await Promise.all([
    fetchForumCategories(),
    getShellSession(),
  ]);

  if (categories.length === 0) {
    redirect("/forum");
  }

  const defaultCategory = params.category?.trim();
  const closeHref =
    defaultCategory && categories.some((c) => c.slug === defaultCategory)
      ? `/forum/${defaultCategory}`
      : "/forum";

  return (
    <div className={`${forumPageClass} gap-6 lg:gap-8`}>
      <div className="-mb-6 hidden lg:block">
        <Breadcrumbs
          items={[
            { label: t("common.home"), href: "/" },
            { label: t("forum.title"), href: "/forum" },
            { label: t("forum.newTopic") },
          ]}
        />
      </div>

      {/* Mobile: a full-screen composer with a close control, no hero. */}
      <div className="flex items-start gap-3">
        <IconButton
          href={closeHref}
          icon="x"
          label={t("forum.closeComposer")}
          variant="soft"
          className="lg:hidden"
        />
        <div className="flex min-w-0 flex-col gap-1">
          <h1 className="type-h1 text-ink">{t("forum.newTopicPageTitle")}</h1>
          <p className="measure type-body text-ink-2">
            {t("forum.newTopicPageDescription")}
          </p>
        </div>
      </div>

      <ForumNewTopicComposer
        categories={categories}
        defaultCategorySlug={defaultCategory}
        settings={settings}
        mustChooseUsername={session.user?.must_choose_username === true}
      />
    </div>
  );
}
