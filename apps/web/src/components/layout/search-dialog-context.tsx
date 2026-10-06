"use client";

import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useId,
  useRef,
  useState,
} from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { SEARCH_DIRECTORY_SECTIONS } from "@/components/layout/search-directory-sections";
import { HEADER_SEARCH_INPUT_ID } from "@/components/layout/header-search";
import { Button, IconButton, buttonClassName } from "@/components/ui/button";
import { Icon } from "@/components/ui/icons";
import { SEARCH_QUERY_HINT } from "@/components/directory/filter-form";
import { directorySearchHref } from "@/lib/search";
import { cn } from "@/lib/cn";
import { t, tFormat } from "@/i18n/t";

type Prefill = {
  q?: string;
  city?: string;
};

type SearchDialogContextValue = {
  openAdvancedSearch: (prefill?: Prefill) => void;
};

const SearchDialogContext = createContext<SearchDialogContextValue | null>(
  null,
);

export function useSearchDialog(): SearchDialogContextValue {
  const ctx = useContext(SearchDialogContext);

  if (!ctx) {
    throw new Error("useSearchDialog must be used within SearchDialogProvider");
  }

  return ctx;
}

const FOCUSABLE =
  'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])';

function SearchModal({
  open,
  onClose,
  q,
  city,
  onQChange,
  onCityChange,
}: {
  open: boolean;
  onClose: () => void;
  q: string;
  city: string;
  onQChange: (v: string) => void;
  onCityChange: (v: string) => void;
}) {
  const titleId = useId();
  const panelRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    if (!open) {
      return;
    }

    // aria-modal promises assistive tech that nothing outside the panel is
    // reachable, so focus is kept inside while open and handed back to
    // whatever opened the dialog once it closes.
    const opener =
      document.activeElement instanceof HTMLElement
        ? document.activeElement
        : null;
    const focusables = () =>
      Array.from(
        panelRef.current?.querySelectorAll<HTMLElement>(FOCUSABLE) ?? [],
      );

    const onKey = (e: KeyboardEvent) => {
      if (e.key === "Escape") {
        onClose();
        return;
      }

      if (e.key !== "Tab") {
        return;
      }

      const items = focusables();
      const first = items[0];
      const last = items[items.length - 1];
      const active = document.activeElement;
      const inside = panelRef.current?.contains(active) ?? false;

      if (e.shiftKey && (active === first || !inside)) {
        e.preventDefault();
        last?.focus();
      } else if (!e.shiftKey && (active === last || !inside)) {
        e.preventDefault();
        first?.focus();
      }
    };

    document.addEventListener("keydown", onKey);
    document.body.style.overflow = "hidden";

    focusables()[0]?.focus();

    return () => {
      document.removeEventListener("keydown", onKey);
      document.body.style.overflow = "";
      if (opener?.isConnected) {
        opener.focus();
      }
    };
  }, [open, onClose]);

  if (!open) {
    return null;
  }

  return (
    <div
      className="fixed inset-0 z-[100] flex items-start justify-center overflow-y-auto bg-ink/50 p-4 pt-[clamp(2rem,12vh,6rem)]"
      role="presentation"
      onMouseDown={(e) => {
        if (e.target === e.currentTarget) {
          onClose();
        }
      }}
    >
      <div
        ref={panelRef}
        role="dialog"
        aria-modal="true"
        aria-labelledby={titleId}
        className={cn(
          "relative w-full max-w-lg rounded-[28px] bg-cream p-6 shadow-sheet",
        )}
        tabIndex={-1}
      >
        <div className="flex items-start justify-between gap-4">
          <h2 id={titleId} className="type-h2 text-ink">
            {t("search.modalTitle")}
          </h2>
          <IconButton
            icon="x"
            label={t("search.close")}
            onClick={onClose}
            className="-mr-2 -mt-2"
          />
        </div>
        <p className="mt-2 type-meta text-ink-2">{t("search.modalSubtitle")}</p>

        <div className="mt-4 space-y-3">
          <div>
            <label
              htmlFor="site-search-q"
              className="type-label mb-2 block text-ink"
            >
              {t("search.nameLabel")}
            </label>
            <input
              id="site-search-q"
              value={q}
              onChange={(e) => onQChange(e.target.value)}
              placeholder={t("home.searchPlaceholder")}
              className="field-control"
              autoComplete="off"
            />
          </div>
          <div>
            <label
              htmlFor="site-search-city"
              className="type-label mb-2 block text-ink"
            >
              {t("search.cityLabel")}
            </label>
            <input
              id="site-search-city"
              value={city}
              onChange={(e) => onCityChange(e.target.value)}
              className="field-control"
              autoComplete="off"
            />
          </div>
          <p className="type-meta text-ink-2">{SEARCH_QUERY_HINT}</p>
        </div>

        <div className="mt-6 space-y-3">
          <p className="type-label text-ink">{t("search.openInSection")}</p>
          <ul className="space-y-2">
            {SEARCH_DIRECTORY_SECTIONS.map((section) => (
              <li key={section.basePath}>
                <Link
                  href={directorySearchHref(section.basePath, q, city)}
                  onClick={onClose}
                  className="card flex items-center gap-3 p-4 no-underline hover:bg-sand"
                >
                  <span className="min-w-0 flex-1">
                    <span className="block font-semibold text-ink">
                      {t(section.titleKey)}
                    </span>
                    <span className="mt-1 block type-meta text-ink-2">
                      {tFormat("search.sectionBlurb", {
                        section: t(section.titleKey),
                      })}
                    </span>
                  </span>
                  <Icon name="chevron-right" size={20} />
                </Link>
              </li>
            ))}
          </ul>
        </div>

        <div className="mt-6 flex flex-wrap gap-2 border-t border-line pt-4">
          <Button type="button" variant="secondary" onClick={onClose}>
            {t("common.cancel")}
          </Button>
          <Link
            href="/search"
            onClick={onClose}
            className={buttonClassName({ variant: "soft" })}
          >
            {t("search.fullPageLink")}
          </Link>
        </div>
      </div>
    </div>
  );
}

export function SearchDialogProvider({
  children,
}: {
  children: React.ReactNode;
}) {
  const router = useRouter();
  const [open, setOpen] = useState(false);
  const [q, setQ] = useState("");
  const [city, setCity] = useState("");

  const openAdvancedSearch = useCallback((p?: Prefill) => {
    setQ(typeof p?.q === "string" ? p.q : "");
    setCity(typeof p?.city === "string" ? p.city : "");
    setOpen(true);
  }, []);

  useEffect(() => {
    const onKey = (e: KeyboardEvent) => {
      if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === "k") {
        const target = e.target as HTMLElement | null;
        if (
          target?.closest("input, textarea, select, [contenteditable=true]")
        ) {
          return;
        }
        e.preventDefault();
        // Desktop: jump into the header search pill. Mobile (pill hidden):
        // the search page.
        const headerInput = document.getElementById(HEADER_SEARCH_INPUT_ID);
        if (headerInput instanceof HTMLElement && headerInput.offsetParent) {
          headerInput.focus();
          return;
        }
        router.push("/search");
      }
    };

    document.addEventListener("keydown", onKey);

    return () => document.removeEventListener("keydown", onKey);
  }, [router]);

  const close = useCallback(() => setOpen(false), []);

  return (
    <SearchDialogContext.Provider value={{ openAdvancedSearch }}>
      {children}
      <SearchModal
        open={open}
        onClose={close}
        q={q}
        city={city}
        onQChange={setQ}
        onCityChange={setCity}
      />
    </SearchDialogContext.Provider>
  );
}
