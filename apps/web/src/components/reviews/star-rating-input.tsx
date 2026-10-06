"use client";

import { useRef } from "react";
import { t } from "@/i18n/t";
import { starRatingLabel } from "@/lib/rating";

type StarRatingInputProps = {
  /** null until the visitor picks a rating — there is no default. */
  value: number | null;
  onChange: (value: number) => void;
  disabled?: boolean;
  invalid?: boolean;
  errorId?: string;
};

const STARS = [1, 2, 3, 4, 5] as const;

/**
 * A radio group of five stars (WAI-ARIA radio pattern): one tab stop, arrow keys
 * move and select, Home/End jump to the ends.
 *
 * It used to start at 5 stars, so an untouched form quietly submitted a
 * five-star review. Now nothing is selected until the visitor chooses.
 */
export function StarRatingInput({
  value,
  onChange,
  disabled,
  invalid,
  errorId,
}: StarRatingInputProps) {
  const refs = useRef<(HTMLButtonElement | null)[]>([]);

  function select(star: number) {
    onChange(star);
    refs.current[star - 1]?.focus();
  }

  function handleKeyDown(event: React.KeyboardEvent, star: number) {
    let next: number | null = null;

    switch (event.key) {
      case "ArrowRight":
      case "ArrowUp":
        next = star >= 5 ? 1 : star + 1;
        break;
      case "ArrowLeft":
      case "ArrowDown":
        next = star <= 1 ? 5 : star - 1;
        break;
      case "Home":
        next = 1;
        break;
      case "End":
        next = 5;
        break;
      default:
        return;
    }

    event.preventDefault();
    select(next);
  }

  // Roving tabindex: the selected star, or the first one before any choice.
  const tabStop = value ?? 1;

  return (
    <div className="flex flex-col gap-2">
      <div
        className="flex gap-1"
        role="radiogroup"
        aria-label={t("reviews.rating")}
        aria-required="true"
        aria-invalid={invalid || undefined}
        aria-describedby={invalid ? errorId : undefined}
      >
        {STARS.map((star) => (
          <button
            key={star}
            ref={(element) => {
              refs.current[star - 1] = element;
            }}
            type="button"
            role="radio"
            aria-checked={star === value}
            tabIndex={star === tabStop ? 0 : -1}
            disabled={disabled}
            onClick={() => select(star)}
            onKeyDown={(event) => handleKeyDown(event, star)}
            className={`rounded px-1 text-2xl leading-none transition focus-visible:outline-2 focus-visible:outline-primary ${
              value !== null && star <= value
                ? "text-amber-500"
                : "text-zinc-300 hover:text-amber-300"
            } disabled:cursor-not-allowed disabled:opacity-60`}
            aria-label={starRatingLabel(star)}
          >
            <span aria-hidden>★</span>
          </button>
        ))}
      </div>
      <p className="text-xs text-zinc-500" aria-hidden>
        {value === null
          ? t("reviews.ratingNone")
          : `${value}${t("common.ratingOutOf")}`}
      </p>
    </div>
  );
}
