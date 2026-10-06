import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/field";
import { t } from "@/i18n/t";

const HINT_ID = "search-query-hint";

/**
 * The /search query form: „Име, специјалност или поим“ + „Град“ + Пребарај, a
 * plain GET form to /search. The query is the page's only real control, so it
 * is never collapsed behind a mobile filter toggle, and it takes focus on the
 * empty hub so a visitor arriving from the header search icon can type at
 * once. White card on the apricot band; one row from lg.
 */
export function SearchQueryForm({
  q,
  city,
  autoFocus = false,
}: {
  q?: string;
  city?: string;
  autoFocus?: boolean;
}) {
  return (
    <form
      role="search"
      action="/search"
      method="get"
      className="rounded-card bg-white p-4 shadow-card lg:p-5"
    >
      <div className="grid gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(0,280px)_auto] lg:items-end">
        <Input
          id="search-q"
          name="q"
          type="search"
          label={t("search.queryLabel")}
          defaultValue={q ?? ""}
          autoComplete="off"
          autoFocus={autoFocus}
          aria-describedby={HINT_ID}
        />
        <Input
          id="search-city"
          name="city"
          type="text"
          label={t("search.cityLabel")}
          defaultValue={city ?? ""}
          autoComplete="address-level2"
        />
        <Button
          type="submit"
          size="lg"
          leadingIcon="search"
          fullWidth
          className="lg:h-14 lg:w-auto lg:min-w-40"
        >
          {t("common.search")}
        </Button>
      </div>
      <p id={HINT_ID} className="type-meta mt-3 text-ink-2">
        {t("search.queryHint")}
      </p>
    </form>
  );
}
