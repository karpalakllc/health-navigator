import { Tag, type TagTone } from "@/components/ui/tag";
import { t, type MessageKey } from "@/i18n/t";

const STATUS: Record<string, { key: MessageKey; tone: TagTone }> = {
  pending: { key: "account.statusPending", tone: "tint" },
  approved: { key: "account.statusApproved", tone: "care" },
  rejected: { key: "account.statusRejected", tone: "outline" },
};

/** Moderation status of the member's own topic or reply („на чекање“ …). */
export function ForumStatusTag({ status }: { status: string }) {
  const entry = STATUS[status];

  return (
    <Tag tone={entry?.tone ?? "sand"} className="shrink-0">
      {entry ? t(entry.key) : status}
    </Tag>
  );
}
