import Link from "next/link";
import { ForumAuthorCard } from "@/components/forum/forum-author-card";
import { ForumCommunityRules } from "@/components/forum/forum-community-rules";
import { ForumRelatedTopics } from "@/components/forum/forum-related-topics";
import { ProfileContentCard } from "@/components/design/profile-content-card";
import type { ForumAuthor, ForumTopicListItem } from "@/lib/api/forum";
import type { PublicSettings } from "@/lib/api/settings";
import { t } from "@/i18n/t";

type ForumTopicSidebarProps = {
  author: ForumAuthor;
  categorySlug: string;
  categoryName: string;
  relatedTopics: ForumTopicListItem[];
  settings: Pick<
    PublicSettings,
    "forum_rules_enabled" | "forum_rules_title" | "forum_rules_body"
  >;
  isLoggedIn: boolean;
  redirectPath: string;
};

export function ForumTopicSidebar({
  author,
  categorySlug,
  categoryName,
  relatedTopics,
  settings,
  isLoggedIn,
  redirectPath,
}: ForumTopicSidebarProps) {
  return (
    <aside className="space-y-6 lg:sticky lg:top-24 lg:self-start">
      <ProfileContentCard title={t("forum.authorMember")}>
        <ForumAuthorCard author={author} variant="sidebar" />
      </ProfileContentCard>

      <ProfileContentCard title={t("forum.categories")}>
        <Link
          href={`/forum/${categorySlug}`}
          className="text-sm font-extrabold text-primary hover:underline"
        >
          {categoryName}
        </Link>
      </ProfileContentCard>

      <ForumRelatedTopics topics={relatedTopics} categorySlug={categorySlug} />

      <ForumCommunityRules settings={settings} />

      {!isLoggedIn ? (
        <ProfileContentCard title={t("forum.loginCtaTitle")}>
          <p className="text-sm leading-relaxed text-muted-foreground">{t("forum.loginCtaBody")}</p>
          <Link
            href={`/login?redirect=${encodeURIComponent(redirectPath)}`}
            className="mt-4 inline-flex min-h-[44px] w-full items-center justify-center rounded-xl bg-primary px-5 text-sm font-extrabold text-primary-foreground hover:bg-primary/90"
          >
            {t("forum.guestReplyCta")}
          </Link>
        </ProfileContentCard>
      ) : null}
    </aside>
  );
}
