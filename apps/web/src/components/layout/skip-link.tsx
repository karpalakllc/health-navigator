import { t } from "@/i18n/t";

/** The id of the layout's <main>; the skip link jumps past the header to it. */
export const MAIN_CONTENT_ID = "main";

/**
 * First tab stop on every page: without it a keyboard user tabs through the
 * logo, every nav link, search and the account menu before reaching content.
 */
export function SkipLink() {
  return (
    <a
      href={`#${MAIN_CONTENT_ID}`}
      className="sr-only focus:not-sr-only focus:fixed focus:left-3 focus:top-2 focus:z-[300] focus:inline-flex focus:min-h-12 focus:items-center focus:bg-focus focus:px-4 focus:font-semibold focus:text-focus-ink"
    >
      {t("nav.skipToContent")}
    </a>
  );
}
