import { t, tFormat } from "@/i18n/t";

export function formatForumReplyCount(count: number): string {
  if (count === 1) {
    return t("forum.replyCountOne");
  }

  return tFormat("forum.repliesCount", { count });
}
