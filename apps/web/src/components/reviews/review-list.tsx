import type { PublicReview } from "@/lib/api/types";
import { StarRating } from "@/components/ui/star-rating";

export function ReviewList({ reviews }: { reviews: PublicReview[] }) {
  if (reviews.length === 0) {
    return null;
  }

  return (
    <ul className="grid list-none gap-3.5 p-0">
      {reviews.map((review) => (
        <li key={review.id ?? `${review.author_name}-${review.published_at}`}>
          <article className="content-card rounded-[1.625rem] p-5 transition-shadow hover:shadow-[0_22px_56px_rgb(16_30_36_/_0.1)]">
            <div className="mb-3 flex items-center justify-between gap-3">
              <StarRating value={review.rating} />
              <span className="text-base font-bold text-foreground">{review.author_name}</span>
            </div>
            {review.body ? <p className="text-sm leading-relaxed text-[#4b5864]">{review.body}</p> : null}
          </article>
        </li>
      ))}
    </ul>
  );
}
