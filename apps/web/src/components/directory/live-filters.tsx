"use client";

import { useRouter } from "next/navigation";
import { useCallback, useEffect, useRef, useState, useTransition } from "react";

/** Every directory filter is a string in the URL; "" means "not set". */
export type FilterValues = Record<string, string>;

export type LiveFilters = {
  /** What the controls show: the URL's filters plus edits not yet applied. */
  values: FilterValues;
  /** Change one filter. Text waits for a pause in typing; the rest apply now. */
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
 * change replaces the URL without scrolling, the server renders the new
 * results and count, and the controls follow the URL again. Edits still in
 * flight are kept as a draft on top, so typing is never overwritten by a
 * response for an older keystroke.
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

  useEffect(
    () => () => {
      if (timer.current) clearTimeout(timer.current);
    },
    [],
  );

  const hrefFor = useCallback(
    (next: FilterValues) => filterHref(basePath, next),
    [basePath],
  );

  const navigate = useCallback(
    (next: FilterValues) => {
      if (timer.current) {
        clearTimeout(timer.current);
        timer.current = null;
      }
      startTransition(() => {
        router.replace(hrefFor(next), { scroll: false });
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
          () => navigate(latest.current),
          TYPING_DELAY_MS,
        );
        return;
      }

      navigate(next);
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
      navigate(next);
    },
    [navigate],
  );

  const flush = useCallback(() => {
    if (timer.current) {
      navigate(latest.current);
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
