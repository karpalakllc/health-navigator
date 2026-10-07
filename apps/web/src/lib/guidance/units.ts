import type { NumberUnit, TimeUnit } from "@/lib/api/guidance-v2";

/** Minutes in one of each time unit (a month is 30.4375 days, as in the API). */
const MINUTES: Record<TimeUnit, number> = {
  minutes: 1,
  hours: 60,
  days: 1440,
  weeks: 10080,
  months: 43830,
  years: 525960,
};

export function isTimeUnit(unit: NumberUnit): unit is TimeUnit {
  return unit in MINUTES;
}

/**
 * Converts an answer typed in `from` into the question's own unit and
 * rounds it to 3 decimals, which is what the API stores. A question asks in
 * one canonical unit; `alt_units` only let the visitor type it differently.
 */
export function convertToUnit(
  value: number,
  from: TimeUnit,
  to: TimeUnit,
): number {
  const converted = (value * MINUTES[from]) / MINUTES[to];

  return Math.round(converted * 1000) / 1000;
}

/** "38,5" (Macedonian decimal comma) and "38.5" both parse; anything else is NaN. */
export function parseLocaleNumber(input: string): number {
  const cleaned = input.trim().replace(",", ".");

  if (!/^-?\d+(\.\d+)?$/.test(cleaned)) {
    return Number.NaN;
  }

  return Number(cleaned);
}

/** The stored spelling: no trailing zeros, dot as decimal separator. */
export function formatAnswerNumber(value: number): string {
  return String(Math.round(value * 1000) / 1000);
}
