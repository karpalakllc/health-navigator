import Link from "next/link";
import { t, tFormat } from "@/i18n/t";

/**
 * Shown above results the API found by name similarity because nothing
 * matched exactly („domenika“ → Доминика): says so, and offers the
 * corrected query („Дали мислевте: Доминика?“), which re-runs the search.
 * A status region, so the change is announced after a new search.
 */
export function FuzzySearchNotice({
  q,
  suggestion,
  suggestionHref,
}: {
  q: string;
  suggestion?: string | null;
  suggestionHref?: string;
}) {
  return (
    <div role="status" className="card flex flex-col gap-1 px-5 py-4">
      <p className="type-body text-ink">
        {tFormat("search.fuzzyNoExact", { q: q.trim() })}
      </p>
      {suggestion && suggestionHref ? (
        <p className="type-body text-ink">
          {t("search.didYouMean")}{" "}
          <Link
            href={suggestionHref}
            className="link-underline font-semibold text-ink"
          >
            {suggestion}
          </Link>
          ?
        </p>
      ) : null}
    </div>
  );
}

/**
 * A directory list's URL with the suggestion as its query: the other applied
 * filters stay, the page starts over.
 */
export function suggestionListHref(
  basePath: string,
  applied: Record<string, string>,
  suggestion: string,
): string {
  const params = new URLSearchParams();

  for (const [key, value] of Object.entries({ ...applied, q: suggestion })) {
    if (value) {
      params.set(key, value);
    }
  }

  return `${basePath}?${params.toString()}`;
}
