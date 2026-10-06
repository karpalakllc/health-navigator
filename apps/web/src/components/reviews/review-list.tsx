import { StarRating } from "@/components/ui/star-rating";
import { Monogram } from "@/components/ui/user-avatar";
import type { PublicReview } from "@/lib/api/types";
import { formatMkDate } from "@/lib/mk-date";

/** Review cards: monogram, display name, date, amber stars, reading body. */
export function ReviewList({ reviews }: { reviews: PublicReview[] }) {
  if (reviews.length === 0) {
    return null;
  }

  return (
    <ul className="m-0 grid list-none gap-3 p-0 lg:grid-cols-2 lg:gap-5">
      {reviews.map((review) => {
        const date = formatMkDate(review.published_at);

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
            </article>
          </li>
        );
      })}
    </ul>
  );
}
