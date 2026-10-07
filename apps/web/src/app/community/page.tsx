import type { Metadata } from "next";
import { CommunityContent } from "@/components/levels/community-content";
import { fetchLeaderboards } from "@/lib/api/levels";
import { pageMetadata } from "@/lib/metadata";
import { t } from "@/i18n/t";

export const metadata: Metadata = pageMetadata(
  t("levels.pageTitle"),
  t("levels.pageDescription"),
  { path: "/community" },
);

/**
 * W8-C „Заедница“: last month's top lists and how titles are earned. The
 * lists are fetched with a short revalidation; the API caches them for hours.
 */
export default async function CommunityPage() {
  const boards = await fetchLeaderboards();

  return <CommunityContent boards={boards} />;
}
