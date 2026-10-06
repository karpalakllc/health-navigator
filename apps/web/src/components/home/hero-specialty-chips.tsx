"use client";

import Link from "next/link";
import { useMemo, useRef, useState } from "react";
import { BottomSheet } from "@/components/ui/bottom-sheet";
import { ChipLink } from "@/components/ui/chip";
import { Icon } from "@/components/ui/icons";
import { scriptIncludes } from "@/lib/script-fold";
import { specialtyIcon } from "@/lib/specialty-icons";
import { t, tCount, tFormat } from "@/i18n/t";

export type HeroSpecialty = {
  slug: string;
  name: string;
  doctors_count: number;
};

/** Chips shown before „Сите специјалности“. */
export const HERO_SPECIALTY_CHIPS = 8;

function specialtyHref(slug: string) {
  return `/doctors?specialty=${encodeURIComponent(slug)}`;
}

/**
 * The hero's quick specialties: the eight with the most doctors as chips,
 * then „Сите специјалности“ when there are more. Phones: one row that
 * scrolls sideways (the hero stays short; the last chip peeks to show there
 * is more). Desktop: the chips wrap.
 *
 * „Сите специјалности“ is a link to the doctor directory (without
 * JavaScript, or a modifier click); a plain click opens the full list in a
 * sheet instead — up from the bottom on phones, from the side on desktop —
 * with a filter box (either script) and each specialty's doctor count.
 */
export function HeroSpecialtyChips({
  top,
  all,
}: {
  top: HeroSpecialty[];
  all: HeroSpecialty[];
}) {
  const [open, setOpen] = useState(false);
  const [query, setQuery] = useState("");
  const filterRef = useRef<HTMLInputElement>(null);

  const chips = top.slice(0, HERO_SPECIALTY_CHIPS);
  const full = useMemo(
    () =>
      all
        .filter((s) => s.doctors_count > 0)
        .sort(
          (a, b) =>
            b.doctors_count - a.doctors_count ||
            a.name.localeCompare(b.name, "mk"),
        ),
    [all],
  );
  const hasMore = full.length > chips.length;
  const matches = full.filter((s) => scriptIncludes(s.name, query));

  if (chips.length === 0) {
    return null;
  }

  return (
    <>
      <ul
        aria-label={t("home.quickLinksAria")}
        data-chip-row=""
        className="scroll-row col-span-2 -mx-4 -mb-2.5 mt-3 flex gap-2 overflow-x-auto px-4 py-2.5 sm:-mx-5 sm:px-5 lg:mx-0 lg:mb-0 lg:mt-5 lg:flex-wrap lg:overflow-visible lg:px-0 lg:py-0"
      >
        {chips.map((specialty) => (
          <li key={specialty.slug} className="flex-none">
            <ChipLink
              href={specialtyHref(specialty.slug)}
              icon={specialtyIcon(specialty)}
              className="shadow-none"
            >
              {specialty.name}
            </ChipLink>
          </li>
        ))}
        {hasMore ? (
          <li className="flex-none">
            <Link
              href="/doctors"
              aria-haspopup="dialog"
              aria-expanded={open}
              onClick={(event) => {
                if (
                  event.metaKey ||
                  event.ctrlKey ||
                  event.shiftKey ||
                  event.altKey
                ) {
                  return;
                }
                event.preventDefault();
                setQuery("");
                setOpen(true);
              }}
              className="chip font-semibold"
            >
              <Icon name="list-filter" size={18} />
              <span>{t("homeSearch.allSpecialties")}</span>
            </Link>
          </li>
        ) : null}
      </ul>

      <BottomSheet
        open={open}
        onClose={() => setOpen(false)}
        title={t("homeSearch.specialtiesTitle")}
        initialFocusRef={filterRef}
      >
        <label htmlFor="hero-specialty-filter" className="sr-only">
          {t("homeSearch.specialtiesFilter")}
        </label>
        <div className="relative mt-1">
          <Icon
            name="search"
            size={20}
            className="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-ink-2"
          />
          <input
            ref={filterRef}
            id="hero-specialty-filter"
            type="search"
            autoComplete="off"
            value={query}
            placeholder={t("homeSearch.specialtiesFilter")}
            onChange={(event) => setQuery(event.target.value)}
            className="field-control h-12 w-full rounded-input border border-line-strong bg-white pl-11 pr-3 type-body text-ink placeholder:text-ink-2"
          />
        </div>
        {matches.length === 0 ? (
          <p role="status" className="type-meta mt-4 text-ink-2">
            {tFormat("homeSearch.specialtiesNoMatch", { q: query.trim() })}
          </p>
        ) : null}
        <ul className="mt-3 flex flex-col">
          {matches.map((specialty) => (
            <li key={specialty.slug}>
              <Link
                href={specialtyHref(specialty.slug)}
                onClick={() => setOpen(false)}
                className="flex min-h-14 items-center gap-3 rounded-lg px-2 py-2 text-ink transition-colors hover:bg-sand"
              >
                <span className="flex size-10 flex-none items-center justify-center rounded-full bg-chip-tint">
                  <Icon name={specialtyIcon(specialty)} size={20} />
                </span>
                <span className="min-w-0 flex-1 font-ui font-semibold leading-5">
                  {specialty.name}
                </span>
                <span className="type-meta text-ink-2">
                  {tCount(
                    "homeSections.specialtyDoctors",
                    specialty.doctors_count,
                  )}
                </span>
              </Link>
            </li>
          ))}
        </ul>
      </BottomSheet>
    </>
  );
}
