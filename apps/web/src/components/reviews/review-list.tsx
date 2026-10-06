import { ReportButton } from "@/components/reports/report-button";
import { RemovedPlaceholder } from "@/components/reviews/removed-placeholder";
import { ReviewHelpfulButton } from "@/components/reviews/review-helpful-button";
import { StarRating } from "@/components/ui/star-rating";
import { Monogram } from "@/components/ui/user-avatar";
import {
  isRemovedItem,
  type ReviewListItem,
  type ReviewResponse,
} from "@/lib/api/types";
import { formatMkDate } from "@/lib/mk-date";
import { t, tFormat } from "@/i18n/t";

type ReviewListProps = {
  /** Reviews, and a placeholder where a published one was removed. */
  reviews: ReviewListItem[];
  isLoggedIn?: boolean;
  /** Still a temporary „clen-…“ name: votes go to the username chooser. */
  mustChooseUsername?: boolean;
  /** The viewer's own review: no „Корисно“ or „Пријави“ on it (the API refuses both). */
  viewerReviewId?: number | null;
  /** Where sign-in returns to from „Корисно“ / „Пријави“. */
  returnTo?: string;
};

/**
 * Review cards: monogram, display name, date, amber stars, reading body, the
 * profile's official response when there is one, then the quiet actions
 * („Корисно“, „Пријави“). A removed review keeps its place as a one-line
 * placeholder with the date and the public reason.
 */
export function ReviewList({
  reviews,
  isLoggedIn = false,
  mustChooseUsername = false,
  viewerReviewId = null,
  returnTo = "/",
}: ReviewListProps) {
  if (reviews.length === 0) {
    return null;
  }

  return (
    <ul className="m-0 grid list-none gap-3 p-0 lg:grid-cols-2 lg:gap-5">
      {reviews.map((review) => {
        if (isRemovedItem(review)) {
          return (
            <li key={`removed-${review.id}`}>
              <RemovedPlaceholder item={review} kind="review" />
            </li>
          );
        }

        const date = formatMkDate(review.published_at);
        const own = viewerReviewId !== null && review.id === viewerReviewId;

        return (
          <li key={review.id ?? `${review.author_name}-${review.published_at}`}>
            <article className="card flex h-full flex-col gap-3 p-5">
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
                <StarRating
                  value={review.rating}
                  size="md"
                  className="mt-0.5"
                />
              </header>
              {review.body ? (
                <p className="type-reading text-ink">{review.body}</p>
              ) : null}
              {review.response ? (
                <OfficialResponse response={review.response} />
              ) : null}
              {/* Neither „Корисно“ nor „Пријави“ on the viewer's own review:
                  the API refuses both. */}
              {typeof review.id === "number" && !own ? (
                <div
                  role="group"
                  aria-label={tFormat("reviews.actions", {
                    name: review.author_name,
                  })}
                  className="mt-auto flex flex-wrap items-start justify-between gap-2 pt-1"
                >
                  <ReviewHelpfulButton
                    reviewId={review.id}
                    count={review.helpful_count ?? 0}
                    voted={review.viewer?.has_voted_helpful ?? false}
                    isLoggedIn={isLoggedIn}
                    mustChooseUsername={mustChooseUsername}
                    returnTo={returnTo}
                  />
                  <ReportButton
                    target={{ kind: "review", id: review.id }}
                    label={tFormat("reports.actionReview", {
                      name: review.author_name,
                    })}
                    isLoggedIn={isLoggedIn}
                    returnTo={returnTo}
                  />
                </div>
              ) : null}
            </article>
          </li>
        );
      })}
    </ul>
  );
}

/**
 * The reviewed profile's reply, on a sand inset under the review: labelled
 * with the profile's name, plain text (line breaks kept, never HTML).
 */
export function OfficialResponse({ response }: { response: ReviewResponse }) {
  const date = formatMkDate(response.responded_at);

  // The doctor's own reply („Мој профил“) is labelled apart from one staff
  // entered on the profile's behalf, and signed with the doctor's name.
  const fromDoctor = response.source === "doctor";

  return (
    <section className="flex flex-col gap-1.5 rounded-2xl border-l-4 border-line-strong bg-sand p-4">
      {fromDoctor ? (
        <p className="type-meta font-semibold text-ink-2">
          {t("doctorDashboard.publicLabel")}
        </p>
      ) : null}
      <h3 className="type-label text-ink">
        {fromDoctor && response.responder_name
          ? response.responder_name
          : response.responder_name
            ? tFormat("reviewResponse.title", {
                name: response.responder_name,
              })
            : t("reviewResponse.titleGeneric")}
      </h3>
      {date && response.responded_at ? (
        <time dateTime={response.responded_at} className="type-meta text-ink-2">
          {date}
        </time>
      ) : null}
      <p className="whitespace-pre-line break-words type-reading text-ink">
        {response.body}
      </p>
    </section>
  );
}
