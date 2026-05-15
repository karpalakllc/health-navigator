import type { PublicReview } from "@/lib/api/types";
import { StarRating } from "@/components/ui/star-rating";

export function ReviewList({ reviews }: { reviews: PublicReview[] }) {
  if (reviews.length === 0) {
    return null;
  }

  return (
    <ul className="divide-y divide-zinc-200 rounded-lg border border-zinc-200 bg-white">
      {reviews.map((review) => (
        <li key={review.id ?? `${review.author_name}-${review.published_at}`} className="space-y-2 p-4">
          <div className="flex flex-wrap items-center justify-between gap-2">
            <StarRating value={review.rating} />
            <span className="text-sm font-medium text-zinc-900">{review.author_name}</span>
          </div>
          {review.body ? (
            <p className="text-sm text-zinc-700">{review.body}</p>
          ) : null}
        </li>
      ))}
    </ul>
  );
}
