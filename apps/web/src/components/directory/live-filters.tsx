"use client";

import { useRouter } from "next/navigation";
import { useCallback, useEffect, useRef, useState, useTransition } from "react";

/** Every directory filter is a string in the URL; "" means "not set". */
export type FilterValues = Record<string, string>;

export type LiveFilters = {
  /** What the controls show: the URL's filters plus edits not yet applied. */
  values: FilterValues;
  /**
   * Change one filter. Text waits for a pause in typing and replaces the
   * history entry; every other change applies now as a new entry, so Back
   * undoes it.
   */
  set: (name: string, value: string, options?: { debounce?: boolean }) => void;
  /** Reset the given filters (all of them by default) and apply. */
  clear: (names?: string[]) => void;
  /** Apply any edit still waiting for its debounce. */
  flush: () => void;
  /** A filter change is on its way (the results are being fetched). */
  pending: boolean;
  /** The list URL for a set of values (page reset to 1, empty ones dropped). */
  hrefFor: (values: FilterValues) => string;
};

const TYPING_DELAY_MS = 600;

export function filterHref(basePath: string, values: FilterValues): string {
  const params = new URLSearchParams();

  for (const [key, raw] of Object.entries(values)) {
    const value = raw.trim();
    if (value !== "") {
      params.set(key, value);
    }
  }

  const query = params.toString();

  return query ? `${basePath}?${query}` : basePath;
}

/**
 * Filters that apply as you change them (shared requirement: "filters apply
 * live and keep the scroll position"). The URL stays the source of truth: a
 * change navigates without scrolling, the server renders the new results and
 * count, and the controls follow the URL again. Edits still in flight are
 * kept as a draft on top, so typing is never overwritten by a response for an
 * older keystroke.
 *
 * History: a discrete change (chip, switch, select, sort, „Исчисти“, a
 * submitted search) pushes an entry, so the browser's Back undoes it; the
 * debounced text applied while typing only replaces the current entry, so
 * Back doesn't step through every pause. A pending debounce is dropped as soon
 * as the person follows a link or goes Back, so a late timer can't pull them
 * back to the list.
 */
export function useLiveFilters({
  basePath,
  applied,
}: {
  basePath: string;
  applied: FilterValues;
}): LiveFilters {
  const router = useRouter();
  const [pending, startTransition] = useTransition();
  const [draft, setDraft] = useState<FilterValues>({});
  const values = { ...applied, ...draft };

  const latest = useRef(values);
  const timer = useRef<ReturnType<typeof setTimeout> | null>(null);

  useEffect(() => {
    latest.current = values;
  });

  useEffect(() => {
    function cancelTyping() {
      if (timer.current) {
        clearTimeout(timer.current);
        timer.current = null;
      }
    }
    // Navigation is starting elsewhere: a link click (Next's <Link> included,
    // which reaches the document before it routes) or Back/Forward.
    function onClick(event: MouseEvent) {
      const target = event.target;
      if (target instanceof Element && target.closest("a[href]")) {
        cancelTyping();
      }
    }

    document.addEventListener("click", onClick, true);
    window.addEventListener("popstate", cancelTyping);

    return () => {
      cancelTyping();
      document.removeEventListener("click", onClick, true);
      window.removeEventListener("popstate", cancelTyping);
    };
  }, []);

  const hrefFor = useCallback(
    (next: FilterValues) => filterHref(basePath, next),
    [basePath],
  );

  const navigate = useCallback(
    (next: FilterValues, history: "push" | "replace") => {
      if (timer.current) {
        clearTimeout(timer.current);
        timer.current = null;
      }
      startTransition(() => {
        router[history](hrefFor(next), { scroll: false });
        // Dropped together with the navigation; keystrokes typed meanwhile
        // are functional updates and survive the rebase.
        setDraft({});
      });
    },
    [hrefFor, router],
  );

  const set = useCallback(
    (name: string, value: string, options: { debounce?: boolean } = {}) => {
      setDraft((current) => ({ ...current, [name]: value }));
      const next = { ...latest.current, [name]: value };
      latest.current = next;

      if (options.debounce) {
        if (timer.current) clearTimeout(timer.current);
        timer.current = setTimeout(
          () => navigate(latest.current, "replace"),
          TYPING_DELAY_MS,
        );
        return;
      }

      navigate(next, "push");
    },
    [navigate],
  );

  const clear = useCallback(
    (names?: string[]) => {
      const next = { ...latest.current };
      for (const key of names ?? Object.keys(next)) {
        next[key] = "";
      }
      setDraft(next);
      latest.current = next;
      navigate(next, "push");
    },
    [navigate],
  );

  const flush = useCallback(() => {
    // An explicit „apply“ (Enter, „Прикажи N резултати“) is a step of its own.
    if (timer.current) {
      navigate(latest.current, "push");
    }
  }, [navigate]);

  return { values, set, clear, flush, pending, hrefFor };
}

/** How many filters are set, for „Филтри (N)“; `ignore` = e.g. the query. */
export function activeFilterCount(
  values: FilterValues,
  ignore: string[] = [],
): number {
  return Object.entries(values).filter(
    ([key, value]) => !ignore.includes(key) && value.trim() !== "",
  ).length;
}
