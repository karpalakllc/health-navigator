import { ForumCategoryCard } from "@/components/forum/forum-category-card";
import { ForumCommunityRules } from "@/components/forum/forum-community-rules";
import { ForumSafetyNotice } from "@/components/forum/forum-safety-notice";
import { EmptyState } from "@/components/directory/empty-state";
import { PageHeader } from "@/components/directory/page-header";
import { PageShell } from "@/components/ui/page-shell";
import { pageMetadata } from "@/lib/metadata";
import { fetchForumCategories } from "@/lib/api/forum";
import { t } from "@/i18n/t";
import type { Metadata } from "next";

export const metadata: Metadata = pageMetadata(
  t("forum.title"),
  t("forum.description"),
);

export default async function ForumPage() {
  const categories = await fetchForumCategories();

  return (
    <PageShell>
      <PageHeader title={t("forum.title")} description={t("forum.description")} />
      <ForumSafetyNotice />
      <ForumCommunityRules />

      {categories.length === 0 ? (
        <EmptyState title={t("forum.noCategories")} />
      ) : (
        <div className="space-y-4">
          <p className="text-sm text-muted-foreground">{t("forum.browseCategories")}</p>
          <ul className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            {categories.map((category) => (
              <li key={category.slug}>
                <ForumCategoryCard category={category} />
              </li>
            ))}
          </ul>
        </div>
      )}
    </PageShell>
  );
}
