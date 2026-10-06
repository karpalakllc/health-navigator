"use client";

import { useRef } from "react";
import { STAR_PATH } from "@/components/ui/icons";
import { cn } from "@/lib/cn";
import { t } from "@/i18n/t";
import { starRatingLabel } from "@/lib/rating";

type StarRatingInputProps = {
  /** null until the visitor picks a rating — there is no default. */
  value: number | null;
  onChange: (value: number) => void;
  disabled?: boolean;
  invalid?: boolean;
  errorId?: string;
  /** The group's accessible name; the overall rating's by default. */
  label?: string;
  /** Overall stars are required; the optional aspect ratings are not. */
  required?: boolean;
  /** Smaller stars (44px targets) and no „x / 5“ line, for aspect rows. */
  compact?: boolean;
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
  label = t("reviews.rating"),
  required = true,
  compact = false,
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
        // star-input: hovering previews the fill up to the pointed star
        // (globals.css); the click still sets the value.
        className="star-input -ml-2 flex"
        role="radiogroup"
        aria-label={label}
        aria-required={required || undefined}
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
            className={cn(
              "star-input-star inline-flex items-center justify-center rounded-full text-star transition-colors",
              compact ? "size-11" : "size-12",
              "hover:bg-sand disabled:cursor-not-allowed disabled:hover:bg-transparent",
            )}
            aria-label={starRatingLabel(star)}
          >
            <svg
              width={compact ? 26 : 32}
              height={compact ? 26 : 32}
              viewBox="0 0 24 24"
              aria-hidden="true"
              focusable="false"
            >
              <path
                d={STAR_PATH}
                fill={value !== null && star <= value ? "currentColor" : "none"}
                stroke={
                  value !== null && star <= value
                    ? "currentColor"
                    : "var(--color-line-strong)"
                }
                strokeWidth={1.4}
                strokeLinejoin="round"
              />
            </svg>
          </button>
        ))}
      </div>
      {compact ? null : (
        <p className="type-meta text-ink-2" aria-hidden>
          {value === null
            ? t("reviews.ratingNone")
            : `${value}${t("common.ratingOutOf")}`}
        </p>
      )}
    </div>
  );
}
