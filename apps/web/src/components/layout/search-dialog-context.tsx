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
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import { SEARCH_QUERY_HINT, filterInputClassName } from "@/components/directory/filter-form";
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

const SearchDialogContext = createContext<SearchDialogContextValue | null>(null);

export function useSearchDialog(): SearchDialogContextValue {
  const ctx = useContext(SearchDialogContext);

  if (!ctx) {
    throw new Error("useSearchDialog must be used within SearchDialogProvider");
  }

  return ctx;
}

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

    const onKey = (e: KeyboardEvent) => {
      if (e.key === "Escape") {
        onClose();
      }
    };

    document.addEventListener("keydown", onKey);
    document.body.style.overflow = "hidden";

    const focusable = panelRef.current?.querySelector<HTMLElement>(
      'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])',
    );
    focusable?.focus();

    return () => {
      document.removeEventListener("keydown", onKey);
      document.body.style.overflow = "";
    };
  }, [open, onClose]);

  if (!open) {
    return null;
  }

  return (
    <div
      className="fixed inset-0 z-[100] flex items-start justify-center overflow-y-auto bg-black/50 p-4 pt-[clamp(2rem,12vh,6rem)] backdrop-blur-sm"
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
          "relative w-full max-w-lg rounded-2xl border border-border bg-card p-6 shadow-xl",
        )}
        tabIndex={-1}
      >
        <div className="flex items-start justify-between gap-4">
          <h2 id={titleId} className="text-lg font-semibold text-foreground">
            {t("search.modalTitle")}
          </h2>
          <button
            type="button"
            onClick={onClose}
            className="rounded-lg p-2 text-muted-foreground hover:bg-secondary hover:text-foreground"
            aria-label={t("search.close")}
          >
            <CloseIcon className="h-5 w-5" />
          </button>
        </div>
        <p className="mt-2 text-sm text-muted-foreground">{t("search.modalSubtitle")}</p>

        <div className="mt-4 space-y-3">
          <div>
            <label htmlFor="site-search-q" className="mb-1 block text-sm font-medium text-foreground">
              {t("search.nameLabel")}
            </label>
            <input
              id="site-search-q"
              value={q}
              onChange={(e) => onQChange(e.target.value)}
              placeholder={t("home.searchPlaceholder")}
              className={cn(filterInputClassName, "w-full")}
              autoComplete="off"
            />
          </div>
          <div>
            <label htmlFor="site-search-city" className="mb-1 block text-sm font-medium text-foreground">
              {t("search.cityLabel")}
            </label>
            <input
              id="site-search-city"
              value={city}
              onChange={(e) => onCityChange(e.target.value)}
              className={cn(filterInputClassName, "w-full")}
              autoComplete="off"
            />
          </div>
          <p className="text-xs text-muted-foreground">{SEARCH_QUERY_HINT}</p>
        </div>

        <div className="mt-6 space-y-3">
          <p className="text-sm font-medium text-foreground">{t("search.openInSection")}</p>
          <ul className="space-y-2">
            {SEARCH_DIRECTORY_SECTIONS.map((section) => (
              <li key={section.basePath}>
                <Link
                  href={directorySearchHref(section.basePath, q, city)}
                  onClick={onClose}
                  className="block"
                >
                  <Card className="card-hover border-border p-4 transition-colors hover:border-primary/30">
                    <span className="font-medium text-foreground">{t(section.titleKey)}</span>
                    <span className="mt-1 block text-sm text-muted-foreground">
                      {tFormat("search.sectionBlurb", {
                        section: t(section.titleKey),
                      })}
                    </span>
                  </Card>
                </Link>
              </li>
            ))}
          </ul>
        </div>

        <div className="mt-4 flex flex-wrap gap-2 border-t border-border pt-4">
          <Button type="button" variant="outline" className="text-sm" onClick={onClose}>
            {t("common.cancel")}
          </Button>
          <Link
            href="/search"
            onClick={onClose}
            className="inline-flex items-center justify-center rounded-xl border border-border bg-card px-4 py-2.5 text-sm font-semibold text-foreground transition hover:bg-secondary"
          >
            {t("search.fullPageLink")}
          </Link>
        </div>
      </div>
    </div>
  );
}

function CloseIcon({ className }: { className?: string }) {
  return (
    <svg className={className} fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2} aria-hidden>
      <path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" />
    </svg>
  );
}

export function SearchDialogProvider({ children }: { children: React.ReactNode }) {
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
        if (target?.closest("input, textarea, select, [contenteditable=true]")) {
          return;
        }
        e.preventDefault();
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
