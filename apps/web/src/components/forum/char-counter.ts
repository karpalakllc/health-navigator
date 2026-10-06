import { tFormat } from "@/i18n/t";

/*
 * Grouped by hand: Intl's "mk-MK" data differs between Node (10.000) and some
 * browsers (10,000), which made the server and client counters disagree.
 */
function group(value: number): string {
  return String(value).replace(/\B(?=(\d{3})+(?!\d))/g, ".");
}

/** „120 / 10.000“ — Macedonian digit grouping. */
export function formatCharCounter(count: number, max: number): string {
  return tFormat("forum.charCounter", {
    count: group(count),
    max: group(max),
  });
}
