"use client";

import { useMemo, useState } from "react";
import type { MessageKey } from "@/i18n/t";
import { t } from "@/i18n/t";
import { cn } from "@/lib/cn";
import { homeHeroSearchClass } from "@/components/ui/layout";

type SearchScope = "all" | "symptoms" | "doctors" | "products" | "pharmacies";

const SCOPES: {
  id: SearchScope;
  labelKey: MessageKey;
  action: string;
  sendQuery: boolean;
}[] = [
  {
    id: "all",
    labelKey: "home.searchScopeAll",
    action: "/search",
    sendQuery: true,
  },
  {
    id: "symptoms",
    labelKey: "home.searchScopeSymptoms",
    action: "/guidance",
    sendQuery: false,
  },
  {
    id: "doctors",
    labelKey: "home.searchScopeDoctors",
    action: "/doctors",
    sendQuery: true,
  },
  {
    id: "products",
    labelKey: "home.searchScopeProducts",
    action: "/products",
    sendQuery: true,
  },
  {
    id: "pharmacies",
    labelKey: "home.searchScopePharmacies",
    action: "/pharmacies",
    sendQuery: true,
  },
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
    <div className={homeHeroSearchClass}>
      <div
        className="rounded-[1.75rem] border border-white/[0.86] bg-white/90 p-4 shadow-[0_28px_90px_rgb(16_30_36_/_0.12)]"
        role="tablist"
        aria-label={t("home.searchScopesAria")}
      >
        <div className="mb-3.5 flex flex-wrap justify-center gap-2.5">
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
                  "min-h-10 rounded-full px-4 text-sm font-bold transition-all",
                  selected
                    ? "btn-gradient-primary text-white shadow-[0_12px_24px_rgb(255_87_87_/_0.2)]"
                    : "bg-transparent text-[#5f6b77] hover:bg-[#f1f4f6]",
                )}
              >
                {t(s.labelKey)}
              </button>
            );
          })}
        </div>

        <form action={active.action} method="get">
          {active.sendQuery ? (
            <div className="flex flex-col gap-3 sm:flex-row sm:items-stretch">
              <label className="flex min-h-[60px] flex-1 items-center gap-3 rounded-[1.25rem] border border-border bg-[#fbfcfc] px-[18px]">
                <SearchIcon
                  className="h-5 w-5 shrink-0 text-[#7b8693]"
                  aria-hidden
                />
                <input
                  name="q"
                  type="search"
                  placeholder={t(placeholderKey(scope))}
                  autoComplete="off"
                  className="w-full border-0 bg-transparent text-base text-foreground outline-none placeholder:text-muted-foreground"
                  aria-label={t(placeholderKey(scope))}
                />
              </label>
              <button
                type="submit"
                className="btn-gradient-primary inline-flex min-h-[60px] shrink-0 items-center justify-center rounded-[1.25rem] px-6 text-sm font-extrabold text-white transition hover:brightness-105 sm:min-w-[120px]"
              >
                {t("home.searchButton")}
              </button>
            </div>
          ) : (
            <div className="flex flex-col gap-3 rounded-[1.25rem] border border-border bg-[#fbfcfc] px-5 py-4 text-center sm:flex-row sm:items-center sm:justify-between sm:text-left">
              <p className="text-sm text-muted-foreground">
                {t("home.searchSymptomsHint")}
              </p>
              <button
                type="submit"
                className="btn-gradient-primary inline-flex h-11 shrink-0 items-center justify-center rounded-[1.25rem] px-6 text-sm font-extrabold text-white"
              >
                {t("home.searchSymptomsCta")}
              </button>
            </div>
          )}
        </form>
      </div>
    </div>
  );
}

function SearchIcon({ className }: { className?: string }) {
  return (
    <svg
      className={className}
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      strokeWidth="2"
    >
      <circle cx="11" cy="11" r="7" />
      <path d="M20 20l-3.5-3.5" strokeLinecap="round" />
    </svg>
  );
}
