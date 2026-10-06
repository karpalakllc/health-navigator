import { Tag, type TagTone } from "@/components/ui/tag";
import type { IconName } from "@/components/ui/icons";
import { t, type MessageKey } from "@/i18n/t";

const STATUS: Record<
  string,
  { label: MessageKey; tone: TagTone; icon: IconName }
> = {
  pending: { label: "account.statusPending", tone: "tint", icon: "clock" },
  approved: { label: "account.statusApproved", tone: "care", icon: "check" },
  rejected: { label: "account.statusRejected", tone: "outline", icon: "x" },
};

/**
 * Moderation status of the member's own content as a D2a tag: pending is the
 * soft tint, approved care green, rejected the neutral outline — never red,
 * a rejection is information, not an alarm. The icon doubles the colour.
 */
export function ModerationStatusTag({ status }: { status: string }) {
  const known = STATUS[status];

  if (!known) {
    return <Tag tone="sand">{status}</Tag>;
  }

  return (
    <Tag tone={known.tone} icon={known.icon}>
      {t(known.label)}
    </Tag>
  );
}
