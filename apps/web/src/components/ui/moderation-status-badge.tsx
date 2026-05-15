import { t, type MessageKey } from "@/i18n/t";

const statusKeys: Record<string, MessageKey> = {
  pending: "account.statusPending",
  approved: "account.statusApproved",
  rejected: "account.statusRejected",
};

const statusStyles: Record<string, string> = {
  pending: "bg-amber-50 text-amber-900 ring-amber-200",
  approved: "bg-green-50 text-green-900 ring-green-200",
  rejected: "bg-zinc-100 text-zinc-700 ring-zinc-200",
};

type ModerationStatusBadgeProps = {
  status: string;
};

export function ModerationStatusBadge({ status }: ModerationStatusBadgeProps) {
  const labelKey = statusKeys[status];
  const label = labelKey ? t(labelKey) : status;

  return (
    <span
      className={`inline-flex rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset ${
        statusStyles[status] ?? "bg-zinc-100 text-zinc-700 ring-zinc-200"
      }`}
    >
      {label}
    </span>
  );
}
