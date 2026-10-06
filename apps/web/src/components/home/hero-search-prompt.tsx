"use client";

import Link from "next/link";
import { HEADER_SEARCH_INPUT_ID } from "@/components/layout/header-search";
import { Icon } from "@/components/ui/icons";
import { t } from "@/i18n/t";

/**
 * Desktop hero prompt: the header search pill is the search on desktop, so the
 * hero offers a large white pill that moves focus into it instead of a second
 * search field. Without JavaScript (or if the header input is missing) it is a
 * plain link to /search.
 */
export function HeroSearchPrompt() {
  return (
    <Link
      href="/search"
      onClick={(event) => {
        const input = document.getElementById(HEADER_SEARCH_INPUT_ID);
        if (input instanceof HTMLInputElement && input.offsetParent !== null) {
          event.preventDefault();
          input.focus();
        }
      }}
      className="type-button inline-flex min-h-14 items-center gap-3 rounded-pill bg-white pl-5 pr-7 text-ink shadow-card hover:bg-sand"
    >
      <Icon name="search" size={24} />
      <span>{t("home.heroSearchPrompt")}</span>
    </Link>
  );
}
