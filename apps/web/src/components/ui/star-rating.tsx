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
}: {
  className?: string;
  filled: boolean;
}) {
  return (
    <svg
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
  const clamped = Math.min(max, Math.max(0, Math.round(value)));
  const filledTone = tone === "amber" ? "text-amber-500" : "text-primary";

  return (
    <span
      className="inline-flex items-center gap-0.5"
      role="img"
      aria-label={`${clamped} / ${max}`}
    >
      {Array.from({ length: max }, (_, index) => (
        <StarIcon
          key={index}
          filled={index < clamped}
          className={
            index < clamped
              ? `shrink-0 ${filledTone} ${iconSize[size]}`
              : `shrink-0 text-muted-foreground/40 ${iconSize[size]}`
          }
        />
      ))}
    </span>
  );
}
