/*
 * Tag colour roles: 32px pills, 15/500. Contrast (all ≥4.5:1):
 * sand/ink 13.3, care-tint/care 5.4, chip-tint/ink 13.3, outline ink-2 on
 * white 7.6, ink/white 15.6.
 *
 * Its own module so the client DisclosureBadge and the server-safe Tag share
 * it without importing each other.
 */
export const tagTones = {
  /** „Автор“, „16 години искуство“, categories, services. */
  sand: "bg-sand text-ink",
  /** „Прима нови пациенти“, „Отворено“, „Проверена посета“, verified. */
  care: "bg-care-tint text-care",
  /** „Без одговор“ and other soft-brand labels. */
  tint: "bg-chip-tint text-ink",
  /** „Истакнат“, „Спонзорирано“ — deliberately neutral, never coral. */
  outline:
    "bg-white text-ink-2 shadow-[inset_0_0_0_1px_var(--color-line-strong)]",
  /** „Денес“, counters. */
  ink: "bg-ink text-white",
  /** On apricot / sand surfaces. */
  white: "bg-white text-ink",
} as const;

export type TagTone = keyof typeof tagTones;
