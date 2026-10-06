import Link from "next/link";
import { Icon } from "@/components/ui/icons";
import type { RemovedItem } from "@/lib/api/types";
import { formatMkDate } from "@/lib/mk-date";
import { removalCategoryLabel } from "@/lib/review-integrity";
import { tFormat, t } from "@/i18n/t";

/**
 * Where a published review or forum reply was removed: one quiet line with
 * the date and the public reason, so a removal is never silent. Nothing of the
 * removed text, rating or author is shown (the API does not send it).
 */
export function RemovedPlaceholder({
  item,
  kind,
}: {
  item: RemovedItem;
  kind: "review" | "reply";
}) {
  const date = formatMkDate(item.removed_at);
  const category = removalCategoryLabel(item.removal_category);
  const text = date
    ? tFormat(
        kind === "review"
          ? "integrity.removedReview"
          : "integrity.removedReply",
        { date, category },
      )
    : tFormat(
        kind === "review"
          ? "integrity.removedReviewNoDate"
          : "integrity.removedReplyNoDate",
        { category },
      );

  return (
    <article className="flex items-start gap-3 rounded-[20px] border border-dashed border-line-strong bg-sand px-4 py-3 text-ink-2">
      <Icon name="shield-check" size={20} className="mt-0.5 shrink-0" />
      <p className="type-meta">
        {text}{" "}
        <Link
          href="/transparency#moderacija"
          className="link-underline font-semibold text-ink"
        >
          {t("integrity.removedHow")}
        </Link>
      </p>
    </article>
  );
}
