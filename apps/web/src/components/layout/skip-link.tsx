import { t } from "@/i18n/t";

/** The id of the layout's <main>; the skip link jumps past the header to it. */
export const MAIN_CONTENT_ID = "main";

/**
 * First tab stop on every page: without it a keyboard user tabs through the
 * logo, every nav link, search and the account menu before reaching content.
 * Hidden until focused, then a small ink pill (white text, 15.6:1) that
 * carries the global focus ring.
 */
export function SkipLink() {
  return (
    <a
      href={`#${MAIN_CONTENT_ID}`}
      className="sr-only focus:not-sr-only focus:fixed focus:left-3 focus:top-2 focus:z-[300] focus:inline-flex focus:min-h-11 focus:items-center focus:rounded-pill focus:bg-ink focus:px-4 focus:type-label focus:text-white"
    >
      {t("nav.skipToContent")}
    </a>
  );
}
