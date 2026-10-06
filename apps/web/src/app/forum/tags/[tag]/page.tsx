import { notFound } from "next/navigation";
import type { Metadata } from "next";
import { Breadcrumbs } from "@/components/directory/breadcrumbs";
import { Pagination } from "@/components/directory/pagination";
import { ForumRulesCard } from "@/components/forum/forum-rules-band";
import {
  ForumColumns,
  ForumEmpty,
  ForumPageHead,
  forumPageClass,
} from "@/components/forum/forum-layout";
import { ForumTopicList } from "@/components/forum/forum-topic-row";
import { JsonLd } from "@/components/seo/json-ld";
import { BackLink } from "@/components/ui/back-link";
import { fetchForumTagPage } from "@/lib/api/forum";
import { parseListPage } from "@/lib/api/directory-cache-policy";
import { isModuleOn } from "@/lib/api/public-settings";
import { ApiRequestError } from "@/lib/api/server";
import { fetchPublicSettings } from "@/lib/api/settings";
import { forumTagMeta, isIndexableTag, pageMetadata } from "@/lib/metadata";
import { absoluteUrl } from "@/lib/site-url";
import { breadcrumbJsonLd } from "@/lib/structured-data";
import { t, tCount, tFormat } from "@/i18n/t";

type TagPageProps = {
  params: Promise<{ tag: string }>;
  searchParams: Promise<{ page?: string }>;
};

export async function generateMetadata({
  params,
  searchParams,
}: TagPageProps): Promise<Metadata> {
  const { tag: slug } = await params;
  const page = parseListPage((await searchParams).page);
  const settings = await fetchPublicSettings();
  const basePath = `/forum/tags/${slug}`;

  if (!settings.public_forum) {
    return pageMetadata(t("forum.title"), undefined, { noIndex: true });
  }

  try {
    const { tag } = await fetchForumTagPage(slug, page);
    const { title, description } = forumTagMeta(tag);

    return pageMetadata(title, description, {
      path: page > 1 ? `${basePath}?page=${page}` : basePath,
      // Fewer than three topics is a thin page: kept out of the index (and
      // the sitemap) but its links are still followed (docs/seo.md).
      ...(isIndexableTag(tag.topics_count)
        ? {}
        : { noIndex: true, follow: true }),
    });
  } catch (error) {
    if (error instanceof ApiRequestError && error.status === 404) {
      notFound();
    }

    return pageMetadata(t("forum.title"), undefined, {
      path: basePath,
      noIndex: true,
      follow: true,
    });
  }
}

export default async function ForumTagPage({
  params,
  searchParams,
}: TagPageProps) {
  const settings = await fetchPublicSettings();

  if (!isModuleOn(settings, "public_forum")) {
    notFound();
  }

  const { tag: slug } = await params;
  const page = parseListPage((await searchParams).page);

  let data;

  try {
    data = await fetchForumTagPage(slug, page);
  } catch (error) {
    if (error instanceof ApiRequestError && error.status === 404) {
      notFound();
    }

    throw error;
  }

  const { tag, topics, meta } = data;
  const basePath = `/forum/tags/${tag.slug}`;
  const breadcrumbs = breadcrumbJsonLd([
    { name: t("common.home"), url: absoluteUrl("/") },
    { name: t("forum.title"), url: absoluteUrl("/forum") },
    { name: tag.name },
  ]);

  return (
    <div className={`${forumPageClass} gap-6 lg:gap-8`}>
      {breadcrumbs ? <JsonLd data={breadcrumbs} /> : null}
      <div className="lg:hidden">
        <BackLink href="/forum" label={t("forum.title")} />
      </div>
      <div className="-mb-6 hidden lg:block">
        <Breadcrumbs
          items={[
            { label: t("common.home"), href: "/" },
            { label: t("forum.title"), href: "/forum" },
            { label: tag.name },
          ]}
        />
      </div>

      <ForumPageHead
        title={tFormat("seo.tagPageTitle", { tag: tag.name })}
        lead={tFormat("seo.tagPageLead", { tag: tag.name })}
        meta={tCount("forum.topicsCount", meta.total)}
      />

      <ForumColumns
        main={
          <>
            {topics.length === 0 ? (
              <ForumEmpty title={t("seo.tagNoTopics")} />
            ) : (
              <ForumTopicList
                headingLevel={2}
                label={tag.name}
                topics={topics.map((topic) => ({
                  href: `/forum/${topic.category.slug}/${topic.slug}`,
                  title: topic.title,
                  categoryName: topic.category.name,
                  authorName: topic.author_name,
                  repliesCount: topic.replies_count,
                  lastActivityAt: topic.last_post_at ?? topic.published_at,
                }))}
              />
            )}
            <Pagination
              basePath={basePath}
              currentPage={meta.current_page}
              lastPage={meta.last_page}
              total={meta.total}
              searchParams={{}}
            />
          </>
        }
        aside={
          <aside className="flex flex-col gap-5 lg:sticky lg:top-[calc(var(--header-h)+1.5rem)]">
            <ForumRulesCard settings={settings} />
          </aside>
        }
      />
    </div>
  );
}
