type StarRatingProps = {
  value: number;
  max?: number;
  size?: "sm" | "md";
};

const sizeClass = {
  sm: "text-sm",
  md: "text-base",
} as const;

export function StarRating({ value, max = 5, size = "sm" }: StarRatingProps) {
  const clamped = Math.min(max, Math.max(0, Math.round(value)));

  return (
    <span
      className={`inline-flex gap-0.5 text-amber-500 ${sizeClass[size]}`}
      role="img"
      aria-label={`${clamped} / ${max}`}
    >
      {Array.from({ length: max }, (_, index) => (
        <span
          key={index}
          className={index < clamped ? "opacity-100" : "opacity-20"}
          aria-hidden
        >
          ★
        </span>
      ))}
    </span>
  );
}
