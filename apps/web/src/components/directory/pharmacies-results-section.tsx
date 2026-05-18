"use client";

import { useState } from "react";
import { PharmacyCard } from "@/components/directory/pharmacy-card";
import { cn } from "@/lib/cn";
import type { PharmacyListItem } from "@/lib/api/types";
import { t, tFormat } from "@/i18n/t";

export function PharmaciesResultsSection({
  pharmacies,
  total,
}: {
  pharmacies: PharmacyListItem[];
  total: number;
}) {
  const [view, setView] = useState<"grid" | "list">("grid");

  return (
    <section className="space-y-6">
      <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h2 className="text-2xl font-black tracking-tight text-foreground">{t("pharmacies.resultsTitle")}</h2>
          <p className="mt-1 text-muted-foreground">
            {tFormat("pharmacies.resultsCount", { count: String(total) })}
          </p>
        </div>
        <div className="flex gap-2" role="group" aria-label={t("pharmacies.viewModeAria")}>
          <ViewButton active={view === "grid"} onClick={() => setView("grid")} label={t("pharmacies.viewGrid")}>
            <GridIcon className="h-5 w-5" aria-hidden />
          </ViewButton>
          <ViewButton active={view === "list"} onClick={() => setView("list")} label={t("pharmacies.viewList")}>
            <ListIcon className="h-5 w-5" aria-hidden />
          </ViewButton>
        </div>
      </div>

      <ul
        className={cn(
          "grid list-none gap-[18px] p-0",
          view === "list" ? "grid-cols-1" : "grid-cols-1 md:grid-cols-2 xl:grid-cols-3",
        )}
      >
        {pharmacies.map((pharmacy) => (
          <li key={pharmacy.slug} className="h-full">
            <PharmacyCard pharmacy={pharmacy} layout={view} />
          </li>
        ))}
      </ul>
    </section>
  );
}

function ViewButton({
  active,
  onClick,
  label,
  children,
}: {
  active: boolean;
  onClick: () => void;
  label: string;
  children: React.ReactNode;
}) {
  return (
    <button
      type="button"
      onClick={onClick}
      aria-pressed={active}
      aria-label={label}
      className={cn(
        "inline-flex h-11 w-11 items-center justify-center rounded-[0.875rem] border transition",
        active
          ? "border-primary/20 bg-[#fff1f1] text-primary"
          : "border-border bg-white text-[#71808b] hover:bg-[#f7f9fa]",
      )}
    >
      {children}
    </button>
  );
}

function GridIcon({ className }: { className?: string }) {
  return (
    <svg className={className} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
      <rect x="3" y="3" width="7" height="7" rx="1" />
      <rect x="14" y="3" width="7" height="7" rx="1" />
      <rect x="3" y="14" width="7" height="7" rx="1" />
      <rect x="14" y="14" width="7" height="7" rx="1" />
    </svg>
  );
}

function ListIcon({ className }: { className?: string }) {
  return (
    <svg className={className} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
      <path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01" strokeLinecap="round" />
    </svg>
  );
}
