import { ForumCategoryCard } from "@/components/forum/forum-category-card";
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

      {categories.length === 0 ? (
        <EmptyState title={t("forum.noCategories")} />
      ) : (
        <ul className="grid gap-4 sm:grid-cols-2">
          {categories.map((category) => (
            <li key={category.slug}>
              <ForumCategoryCard category={category} />
            </li>
          ))}
        </ul>
      )}
    </PageShell>
  );
}
