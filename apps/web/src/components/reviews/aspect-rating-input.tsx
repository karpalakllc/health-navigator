"use client";

import { useRef, useState } from "react";
import { StarRatingInput } from "@/components/reviews/star-rating-input";
import { Icon } from "@/components/ui/icons";
import {
  REVIEW_ASPECTS,
  aspectLabel,
  type ReviewAspectKey,
} from "@/lib/review-integrity";
import { t, tFormat } from "@/i18n/t";

export type AspectRatings = Partial<Record<ReviewAspectKey, number>>;

/**
 * The optional aspect ratings of the review form, folded away by default so
 * the form stays light: a native <details> („Дополнителни оценки (по
 * желба)“), then one five-star radio group per aspect (the same keyboard
 * pattern as the overall stars). Nothing is preselected; „Без оценка“ takes a
 * choice back, which a radio group alone cannot do. The groups are rendered
 * only while it is open, so a folded section adds nothing to the form's
 * accessibility tree; choices made before folding it are kept.
 */
export function AspectRatingInput({
  kind,
  value,
  onChange,
  disabled,
  defaultOpen = false,
}: {
  kind: keyof typeof REVIEW_ASPECTS;
  value: AspectRatings;
  onChange: (next: AspectRatings) => void;
  disabled?: boolean;
  /** Start unfolded, e.g. when editing a review that carried aspect ratings. */
  defaultOpen?: boolean;
}) {
  const aspects = REVIEW_ASPECTS[kind];
  const [open, setOpen] = useState(defaultOpen);
  const rows = useRef<Partial<Record<ReviewAspectKey, HTMLLIElement | null>>>(
    {},
  );

  function set(aspect: ReviewAspectKey, rating: number | null) {
    const next = { ...value };
    if (rating === null) {
      delete next[aspect];
    } else {
      next[aspect] = rating;
    }
    onChange(next);
  }

  return (
    <details
      className="group rounded-2xl border border-line bg-white"
      open={open}
      onToggle={(event) => setOpen(event.currentTarget.open)}
    >
      <summary className="flex min-h-12 cursor-pointer list-none items-center justify-between gap-3 rounded-2xl px-4 py-3 type-label text-ink [&::-webkit-details-marker]:hidden">
        <span>{t("integrity.aspectsToggle")}</span>
        <Icon
          name="chevron-down"
          size={20}
          className="shrink-0 transition-transform group-open:rotate-180 motion-reduce:transition-none"
        />
      </summary>
      {open ? (
        <div className="flex flex-col gap-4 border-t border-line px-4 pb-4 pt-3">
          <p className="type-meta text-ink-2">{t("integrity.aspectsHint")}</p>
          <ul className="m-0 flex list-none flex-col gap-3 p-0">
            {aspects.map((aspect) => {
              const label = aspectLabel(aspect) ?? aspect;
              const current = value[aspect] ?? null;

              return (
                <li
                  key={aspect}
                  ref={(element) => {
                    rows.current[aspect] = element;
                  }}
                  className="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between sm:gap-4"
                >
                  <span className="type-body text-ink" aria-hidden="true">
                    {label}
                  </span>
                  <div className="flex flex-wrap items-center gap-2">
                    <StarRatingInput
                      value={current}
                      onChange={(rating) => set(aspect, rating)}
                      disabled={disabled}
                      label={label}
                      required={false}
                      compact
                    />
                    {current !== null ? (
                      <button
                        type="button"
                        onClick={() => {
                          set(aspect, null);
                          // The button goes away with the choice: keep focus
                          // in the row, on its (now unchecked) first star.
                          rows.current[aspect]
                            ?.querySelector<HTMLElement>('[role="radio"]')
                            ?.focus();
                        }}
                        disabled={disabled}
                        aria-label={tFormat("integrity.aspectClearLabel", {
                          aspect: label,
                        })}
                        className="inline-flex min-h-12 items-center rounded-full px-3 type-meta font-semibold text-ink-2 underline decoration-line-strong underline-offset-4 hover:text-ink"
                      >
                        {t("integrity.aspectClear")}
                      </button>
                    ) : null}
                  </div>
                </li>
              );
            })}
          </ul>
        </div>
      ) : null}
    </details>
  );
}
