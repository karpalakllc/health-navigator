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
      className="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-[200] focus:rounded-xl focus:bg-card focus:px-4 focus:py-2.5 focus:text-sm focus:font-semibold focus:text-foreground focus:shadow-lg focus:outline focus:outline-2 focus:outline-offset-2 focus:outline-ring"
    >
      {t("nav.skipToContent")}
    </a>
  );
}
