"use client";

import { useMemo, useState } from "react";
import type { MessageKey } from "@/i18n/t";
import { t } from "@/i18n/t";
import { cn } from "@/lib/cn";

type SearchScope = "all" | "symptoms" | "doctors" | "products" | "pharmacies";

const SCOPES: {
  id: SearchScope;
  labelKey: MessageKey;
  action: string;
  /** When false, query is not submitted (e.g. symptom triage entry). */
  sendQuery: boolean;
}[] = [
  { id: "all", labelKey: "home.searchScopeAll", action: "/search", sendQuery: true },
  { id: "symptoms", labelKey: "home.searchScopeSymptoms", action: "/guidance", sendQuery: false },
  { id: "doctors", labelKey: "home.searchScopeDoctors", action: "/doctors", sendQuery: true },
  { id: "products", labelKey: "home.searchScopeProducts", action: "/products", sendQuery: true },
  { id: "pharmacies", labelKey: "home.searchScopePharmacies", action: "/pharmacies", sendQuery: true },
];

function placeholderKey(scope: SearchScope): MessageKey {
  switch (scope) {
    case "symptoms":
      return "home.searchPlaceholderSymptoms";
    case "doctors":
      return "home.searchPlaceholderDoctors";
    case "products":
      return "home.searchPlaceholderProducts";
    case "pharmacies":
      return "home.searchPlaceholderPharmacies";
    default:
      return "home.searchPlaceholderUnified";
  }
}

export function HomeHeroSearchClient() {
  const [scope, setScope] = useState<SearchScope>("all");

  const active = useMemo(() => SCOPES.find((s) => s.id === scope)!, [scope]);

  return (
    <div className="mx-auto flex w-full max-w-2xl flex-col gap-4">
      <div
        className="flex flex-wrap justify-center gap-2"
        role="tablist"
        aria-label={t("home.searchScopesAria")}
      >
        {SCOPES.map((s) => {
          const selected = s.id === scope;
          return (
            <button
              key={s.id}
              type="button"
              role="tab"
              aria-selected={selected}
              onClick={() => setScope(s.id)}
              className={cn(
                "rounded-full px-4 py-2 text-sm font-medium transition-colors duration-200",
                selected
                  ? "bg-primary text-primary-foreground shadow-[0_6px_20px_-8px_color-mix(in_srgb,var(--color-primary)_65%,transparent)]"
                  : "bg-secondary/90 text-secondary-foreground hover:bg-secondary",
              )}
            >
              {t(s.labelKey)}
            </button>
          );
        })}
      </div>

      <form action={active.action} method="get" className="relative">
        {active.sendQuery ? (
          <>
            <span className="pointer-events-none absolute left-5 top-1/2 z-10 -translate-y-1/2 text-muted-foreground">
              <SearchIcon className="h-5 w-5" aria-hidden />
            </span>
            <input
              name="q"
              type="search"
              placeholder={t(placeholderKey(scope))}
              autoComplete="off"
              className="h-14 w-full rounded-full border border-border/90 bg-card py-3 pl-14 pr-28 text-[15px] text-foreground shadow-[0_10px_40px_-22px_rgb(15_23_42/0.35)] transition-[box-shadow,border-color] duration-200 placeholder:text-muted-foreground focus:border-primary focus:outline-none focus:ring-4 focus:ring-ring/20"
              aria-label={t(placeholderKey(scope))}
            />
            <button
              type="submit"
              className="absolute right-2 top-1/2 hidden h-10 -translate-y-1/2 rounded-full bg-primary px-5 text-sm font-semibold text-primary-foreground shadow-sm transition hover:bg-primary/92 active:scale-[0.98] motion-reduce:active:scale-100 sm:inline-flex sm:items-center sm:justify-center"
            >
              {t("home.searchButton")}
            </button>
          </>
        ) : (
          <div className="flex flex-col gap-3 rounded-full border border-border/90 bg-card/80 px-6 py-4 text-center shadow-[0_10px_40px_-22px_rgb(15_23_42/0.35)] backdrop-blur-sm sm:flex-row sm:items-center sm:justify-between sm:text-left">
            <p className="text-sm text-muted-foreground">{t("home.searchSymptomsHint")}</p>
            <button
              type="submit"
              className="inline-flex h-11 shrink-0 items-center justify-center rounded-full bg-primary px-6 text-sm font-semibold text-primary-foreground shadow-sm transition hover:bg-primary/92 active:scale-[0.98] motion-reduce:active:scale-100"
            >
              {t("home.searchSymptomsCta")}
            </button>
          </div>
        )}
        {active.sendQuery ? (
          <button
            type="submit"
            className="mt-3 h-11 w-full rounded-full bg-primary text-sm font-semibold text-primary-foreground shadow-sm transition hover:bg-primary/92 active:scale-[0.98] motion-reduce:active:scale-100 sm:hidden"
          >
            {t("home.searchButton")}
          </button>
        ) : null}
      </form>
    </div>
  );
}

function SearchIcon({ className }: { className?: string }) {
  return (
    <svg className={className} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
      <circle cx="11" cy="11" r="7" />
      <path d="M20 20l-3.5-3.5" strokeLinecap="round" />
    </svg>
  );
}
