import { DoctorReplyEditor } from "@/components/doctor-dashboard/doctor-reply-editor";
import { StarRating } from "@/components/ui/star-rating";
import { Monogram } from "@/components/ui/user-avatar";
import type { DashboardReview } from "@/lib/api/doctor-dashboard-types";
import { formatMkDate } from "@/lib/mk-date";

/**
 * A published review of the doctor's own profile, as the public sees it
 * (public name only), with the doctor's reply editor under it.
 */
export function DoctorReviewCard({ review }: { review: DashboardReview }) {
  const date = formatMkDate(review.published_at);

  return (
    <article className="card flex flex-col gap-4 p-5">
      <header className="flex items-start gap-3">
        <Monogram name={review.author_name} size={40} />
        <div className="min-w-0 flex-1">
          <p className="type-body font-semibold text-ink">
            {review.author_name}
          </p>
          {date && review.published_at ? (
            <time
              dateTime={review.published_at}
              className="type-meta text-ink-2"
            >
              {date}
            </time>
          ) : null}
        </div>
        <StarRating value={review.rating} size="md" className="mt-0.5" />
      </header>
      {review.body ? (
        <p className="whitespace-pre-line type-reading text-ink">
          {review.body}
        </p>
      ) : null}
      <DoctorReplyEditor review={review} />
    </article>
  );
}
