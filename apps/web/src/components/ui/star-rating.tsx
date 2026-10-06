import { ratingLabel } from "@/lib/rating";

type StarRatingProps = {
  value: number;
  max?: number;
  size?: "sm" | "md";
  /** Defaults to primary brand color for filled stars. */
  tone?: "primary" | "amber";
};

const iconSize = {
  sm: "h-3.5 w-3.5",
  md: "h-4 w-4",
} as const;

const STAR_PATH =
  "M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z";

function StarIcon({
  className,
  filled,
  "data-fill": dataFill,
}: {
  className?: string;
  filled: boolean;
  "data-fill"?: string;
}) {
  return (
    <svg
      data-fill={dataFill}
      className={className}
      viewBox="0 0 24 24"
      fill={filled ? "currentColor" : "none"}
      stroke="currentColor"
      strokeWidth={filled ? 0 : 1.5}
      strokeLinejoin="round"
      aria-hidden
    >
      <path strokeLinecap="round" strokeLinejoin="round" d={STAR_PATH} />
    </svg>
  );
}

export function StarRating({
  value,
  max = 5,
  size = "sm",
  tone = "primary",
}: StarRatingProps) {
  // Nearest half star: 4.5 draws four and a half, not five (it used to round
  // to whole stars, so every 4.5 average looked like a perfect score).
  const halves = Math.min(max * 2, Math.max(0, Math.round(value * 2)));
  const filledTone = tone === "amber" ? "text-amber-500" : "text-primary";
  const emptyClass = `shrink-0 text-muted-foreground/40 ${iconSize[size]}`;
  const filledClass = `shrink-0 ${filledTone} ${iconSize[size]}`;

  return (
    <span
      className="inline-flex items-center gap-0.5"
      role="img"
      aria-label={ratingLabel(value, max)}
    >
      {Array.from({ length: max }, (_, index) => {
        const fill =
          halves >= (index + 1) * 2
            ? "full"
            : halves === index * 2 + 1
              ? "half"
              : "empty";

        if (fill === "half") {
          return (
            <span
              key={index}
              data-fill="half"
              className={`relative inline-flex shrink-0 ${iconSize[size]}`}
            >
              <StarIcon filled={false} className={emptyClass} />
              <span className="absolute inset-y-0 left-0 w-1/2 overflow-hidden">
                <StarIcon filled className={filledClass} />
              </span>
            </span>
          );
        }

        return (
          <StarIcon
            key={index}
            filled={fill === "full"}
            data-fill={fill}
            className={fill === "full" ? filledClass : emptyClass}
          />
        );
      })}
    </span>
  );
}
