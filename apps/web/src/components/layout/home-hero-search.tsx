"use client";

import { useState } from "react";
import { Button } from "@/components/ui/button";
import { useSearchDialog } from "@/components/layout/search-dialog-context";
import { t } from "@/i18n/t";

export function HomeHeroSearch() {
  const { openSearch } = useSearchDialog();
  const [q, setQ] = useState("");

  return (
    <div className="mx-auto flex max-w-xl flex-col gap-3 sm:flex-row">
      <input
        name="q"
        type="search"
        value={q}
        onChange={(e) => setQ(e.target.value)}
        placeholder={t("home.searchPlaceholder")}
        className="h-12 w-full flex-1 rounded-2xl border border-border bg-card px-4 text-sm shadow-sm focus:border-primary focus:outline-none focus:ring-2 focus:ring-ring/30"
        onKeyDown={(e) => {
          if (e.key === "Enter") {
            e.preventDefault();
            openSearch({ q });
          }
        }}
      />
      <Button
        type="button"
        className="h-12 shrink-0 px-6"
        onClick={() => openSearch({ q })}
      >
        {t("home.searchButton")}
      </Button>
    </div>
  );
}
