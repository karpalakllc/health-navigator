import { redirect } from "next/navigation";
import { Breadcrumbs } from "@/components/directory/breadcrumbs";
import { DirectoryHero } from "@/components/design/directory-hero";
import { ForumNewTopicComposer } from "@/components/forum/forum-new-topic-composer";
import { PageShell } from "@/components/ui/page-shell";
import { PageHeroBleed } from "@/components/design/page-hero-bleed";
import { getSessionToken } from "@/lib/auth/session";
import { fetchForumCategories } from "@/lib/api/forum";
import { fetchPublicSettingsServer } from "@/lib/api/settings";
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
  const [categories, settings] = await Promise.all([
    fetchForumCategories(),
    fetchPublicSettingsServer(),
  ]);

  if (categories.length === 0) {
    redirect("/forum");
  }

  return (
    <>
      <PageHeroBleed>
        <DirectoryHero
          badge={
            <>
              <PlusIcon />
              {t("forum.newTopic")}
            </>
          }
          title={t("forum.newTopicPageTitle")}
          description={t("forum.newTopicPageDescription")}
        />
      </PageHeroBleed>

      <PageShell className="gap-8 pb-16">
        <Breadcrumbs
          items={[
            { label: t("common.home"), href: "/" },
            { label: t("forum.title"), href: "/forum" },
            { label: t("forum.newTopic") },
          ]}
        />
        <ForumNewTopicComposer
          categories={categories}
          defaultCategorySlug={params.category?.trim()}
          settings={settings}
        />
      </PageShell>
    </>
  );
}

function PlusIcon() {
  return (
    <svg
      className="h-4 w-4 text-primary"
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      strokeWidth="2.5"
      aria-hidden
    >
      <path d="M12 5v14M5 12h14" strokeLinecap="round" />
    </svg>
  );
}
