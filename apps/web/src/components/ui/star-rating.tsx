import { useId } from "react";
import { STAR_PATH } from "@/components/ui/icons";
import { cn } from "@/lib/cn";
import { ratingLabel } from "@/lib/rating";

type StarRatingProps = {
  value: number;
  max?: number;
  /** sm 16px (inline meta), md 20px (cards, reviews), lg 24px (summaries). */
  size?: "sm" | "md" | "lg";
  /** legacy — ignored; stars are always amber #a14806. */
  tone?: "primary" | "amber";
  className?: string;
};

const PX = { sm: 16, md: 20, lg: 24 } as const;

function Star({
  size,
  fill,
  clipId,
}: {
  size: number;
  fill: "full" | "half" | "empty";
  clipId: string;
}) {
  const strokeWidth = Math.round(((1.6 * 24) / size) * 100) / 100;

  return (
    <svg
      data-fill={fill}
      width={size}
      height={size}
      viewBox="0 0 24 24"
      aria-hidden="true"
      focusable="false"
      className="shrink-0 text-star"
    >
      {fill === "half" ? (
        <defs>
          <clipPath id={clipId}>
            {/* The star spans x 2.6–21.4; half fills to its visual middle. */}
            <rect x="0" y="0" width="12" height="24" />
          </clipPath>
        </defs>
      ) : null}
      <path
        d={STAR_PATH}
        fill={fill === "full" ? "currentColor" : "none"}
        stroke="currentColor"
        strokeWidth={strokeWidth}
        strokeLinejoin="round"
      />
      {fill === "half" ? (
        <path
          d={STAR_PATH}
          fill="currentColor"
          stroke="currentColor"
          strokeWidth={strokeWidth}
          strokeLinejoin="round"
          clipPath={`url(#${clipId})`}
        />
      ) : null}
    </svg>
  );
}

/**
 * Read-only stars, amber #a14806 (6.1:1 on white, 5.7:1 on cream). Drawn to
 * the nearest half star; the accessible name keeps one decimal („4,5 / 5“),
 * so a 4.5 average is never announced or drawn as five.
 */
export function StarRating({
  value,
  max = 5,
  size = "sm",
  className,
}: StarRatingProps) {
  const uid = useId().replace(/[^a-zA-Z0-9_-]/g, "");
  const halves = Math.min(max * 2, Math.max(0, Math.round(value * 2)));

  return (
    <span
      className={cn("inline-flex items-center gap-0.5", className)}
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

        return (
          <Star
            key={index}
            size={PX[size]}
            fill={fill}
            clipId={`star-${uid}-${index}`}
          />
        );
      })}
    </span>
  );
}
