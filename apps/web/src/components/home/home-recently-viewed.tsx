"use client";

import Link from "next/link";
import { useRef, useState, useSyncExternalStore } from "react";
import { DirectoryAvatar } from "@/components/directory/directory-avatar";
import { buttonClassName } from "@/components/ui/button";
import { Icon } from "@/components/ui/icons";
import { cn } from "@/lib/cn";
import {
  clearRecentlyViewed,
  getRecentlyViewedServerSnapshot,
  getRecentlyViewedSnapshot,
  recentlyViewedPath,
  subscribeRecentlyViewed,
  type RecentlyViewedEntry,
} from "@/lib/recently-viewed";
import { t } from "@/i18n/t";

const KIND_LABEL: Record<RecentlyViewedEntry["kind"], () => string> = {
  doctor: () => t("homeSections.kindDoctor"),
  facility: () => t("homeSections.kindFacility"),
  pharmacy: () => t("homeSections.kindPharmacy"),
};

/**
 * „Последно прегледани“: the profiles this device opened most recently, read
 * from localStorage (lib/recently-viewed.ts). Renders nothing on the server,
 * during hydration and whenever the list is empty, so it can sit anywhere on
 * the home page. A rail on phones (expects the parent's 20px side padding,
 * which it bleeds into), a 4-column grid on desktop.
 */
export function HomeRecentlyViewed({ className }: { className?: string }) {
  const entries = useSyncExternalStore(
    subscribeRecentlyViewed,
    getRecentlyViewedSnapshot,
    getRecentlyViewedServerSnapshot,
  );
  const [status, setStatus] = useState("");
  // Clearing removes the section and the focused button with it; focus
  // lands on this always-present wrapper instead of falling to <body>.
  const wrapperRef = useRef<HTMLDivElement>(null);

  return (
    <div ref={wrapperRef} tabIndex={-1} className="outline-none">
      {/* Outside the section, which disappears on clear, so the
          confirmation is still announced. */}
      <p role="status" className="sr-only">
        {status}
      </p>
      {entries.length > 0 ? (
        <section
          aria-labelledby="home-recent-title"
          className={cn("min-w-0", className)}
        >
          {/* The clear control shares the heading's row: a 44px icon
              button on phones (named by aria-label), icon + „Исчисти“ from sm. */}
          <div className="flex items-center justify-between gap-3">
            <h2 id="home-recent-title" className="type-h2 min-w-0 text-ink">
              {t("homeSections.recentTitle")}
            </h2>
            <button
              type="button"
              aria-label={t("homeSections.recentClearLabel")}
              className={buttonClassName({
                variant: "ghost",
                size: "sm",
                className: "-mr-3 flex-none max-sm:w-11 max-sm:px-0",
              })}
              onClick={() => {
                wrapperRef.current?.focus({ preventScroll: true });
                clearRecentlyViewed();
                setStatus(t("homeSections.recentCleared"));
              }}
            >
              <Icon name="x" size={20} />
              <span className="max-sm:sr-only">
                {t("homeSections.recentClear")}
              </span>
            </button>
          </div>
          <p className="mt-1 type-meta text-ink-2">
            {t("homeSections.recentLead")}
          </p>
          <ul className="scroll-row -mx-5 mt-3 flex gap-3 px-5 pb-4 pt-1 lg:mx-0 lg:mt-5 lg:grid lg:grid-cols-4 lg:gap-6 lg:overflow-visible lg:px-0 lg:pb-0">
            {entries.map((entry) => (
              <li
                key={`${entry.kind}:${entry.slug}`}
                className="flex w-[240px] flex-none lg:w-auto"
              >
                <Link
                  href={recentlyViewedPath(entry)}
                  className="card hover-lift flex min-h-[72px] w-full items-center gap-3 p-3 text-ink lg:p-4"
                >
                  <DirectoryAvatar
                    kind={entry.kind}
                    avatarUrl={entry.avatarUrl}
                    name={entry.name}
                    size={44}
                    shape={entry.kind === "doctor" ? "circle" : "square"}
                  />
                  <span className="min-w-0 flex-1">
                    <span className="block truncate font-ui text-base font-semibold leading-5">
                      {entry.name}
                    </span>{" "}
                    {/* One line in the phone rail; up to two in the
                        narrower desktop grid cells. */}
                    <span className="mt-0.5 block truncate text-[0.9375rem] leading-5 text-ink-2 lg:line-clamp-2 lg:whitespace-normal">
                      {entry.subtitle ?? KIND_LABEL[entry.kind]()}
                    </span>
                  </span>
                </Link>
              </li>
            ))}
          </ul>
        </section>
      ) : null}
    </div>
  );
}
