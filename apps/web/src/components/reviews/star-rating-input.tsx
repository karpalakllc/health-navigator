"use client";

import { t } from "@/i18n/t";

type StarRatingInputProps = {
  value: number;
  onChange: (value: number) => void;
  disabled?: boolean;
};

export function StarRatingInput({ value, onChange, disabled }: StarRatingInputProps) {
  return (
    <div className="flex flex-col gap-2" role="group" aria-label={t("reviews.rating")}>
      <div className="flex gap-1">
        {[1, 2, 3, 4, 5].map((star) => (
          <button
            key={star}
            type="button"
            disabled={disabled}
            onClick={() => onChange(star)}
            className={`rounded px-1 text-2xl leading-none transition ${
              star <= value ? "text-amber-500" : "text-zinc-300 hover:text-amber-300"
            } disabled:cursor-not-allowed disabled:opacity-60`}
            aria-label={`${star}${t("common.ratingOutOf")}`}
            aria-pressed={star === value}
          >
            ★
          </button>
        ))}
      </div>
      <p className="text-xs text-zinc-500">
        {value}
        {t("common.ratingOutOf")}
      </p>
    </div>
  );
}
