"use client";

import Link from "next/link";
import { useEffect, useId, useRef, useState } from "react";
import { Button, buttonClassName } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import { Icon } from "@/components/ui/icons";
import { canShowReviewPrompt } from "@/lib/review-prompt-storage";
import {
  REVIEW_ANCHOR,
  forgetSearchReturn,
  markSearchPromptShown,
  markSearchVisit,
  noteProfileFromSearch,
  searchPromptCandidate,
  skipSearchPrompt,
  type SearchPromptCandidate,
} from "@/lib/review-growth";
import { t, tFormat } from "@/i18n/t";

/**
 * On the search results (doctor list, unified search): notes that the
 * visitor is searching, so a profile opened next knows it came from here.
 * Renders nothing.
 */
export function SearchVisitMarker() {
  useEffect(() => {
    markSearchVisit();
  }, []);

  return null;
}

/**
 * On a doctor profile: remembers the doctor when it was opened from the
 * results, so the results can ask about it once the visitor is back — or
 * forgets it (and never asks about it here) when the viewer already
 * reviewed it or it is their own profile.
 */
export function SearchReturnRecorder({
  slug,
  name,
  eligible,
  reviewed,
}: {
  slug: string;
  name: string;
  /** False on the viewer's own profile, once reviewed, or while they must choose a username. */
  eligible: boolean;
  reviewed: boolean;
}) {
  useEffect(() => {
    if (reviewed) {
      skipSearchPrompt("doctor", slug);
    }

    if (eligible) {
      noteProfileFromSearch(slug, name);
    } else {
      forgetSearchReturn();
    }
  }, [slug, name, eligible, reviewed]);

  return null;
}

/**
 * „Сте биле кај д-р Х? Споделете го вашето искуство“ — back on the results
 * after opening a doctor's profile from them. A calm card at the end of the
 * list (never above the results, so nothing shifts under the reader), no
 * dialog, no focus moved. At most once per session and once a week per
 * device; never again for a doctor after „Не сега“ or a review, and not when
 * the profile's own prompt already asked (lib/review-growth).
 */
export function SearchReviewPrompt({ className }: { className?: string }) {
  const [candidate, setCandidate] = useState<SearchPromptCandidate | null>(
    null,
  );
  const titleId = useId();
  const card = useRef<HTMLDivElement>(null);

  // A frame after hydration: the caps live in this browser's storage.
  useEffect(() => {
    const frame = requestAnimationFrame(() =>
      setCandidate(
        searchPromptCandidate((slug) => canShowReviewPrompt("doctor", slug)),
      ),
    );

    return () => cancelAnimationFrame(frame);
  }, []);

  // Counted (session + weekly cap) only once the card is actually on screen.
  useEffect(() => {
    const element = card.current;

    if (!candidate || !element) {
      return;
    }

    if (typeof IntersectionObserver === "undefined") {
      markSearchPromptShown();
      return;
    }

    const observer = new IntersectionObserver((entries) => {
      if (entries.some((entry) => entry.isIntersecting)) {
        markSearchPromptShown();
        observer.disconnect();
      }
    });
    observer.observe(element);

    return () => observer.disconnect();
  }, [candidate]);

  if (!candidate) {
    return null;
  }

  return (
    <div ref={card} className={className}>
      <Card
        as="aside"
        tone="apricot"
        padding="md"
        aria-labelledby={titleId}
        data-track="search-review-prompt"
        className="motion-fade-in flex flex-col gap-3"
      >
        <div className="flex items-start gap-3">
          <span className="inline-flex size-10 shrink-0 items-center justify-center rounded-full bg-white text-ink">
            <Icon name="star" size={22} />
          </span>
          <div className="flex min-w-0 flex-col gap-1">
            <p id={titleId} className="type-body font-semibold text-ink">
              {tFormat("reviewGrowth.searchPromptTitle", {
                name: candidate.name,
              })}
            </p>
            <p className="type-meta text-ink">
              {t("reviewGrowth.searchPromptLead")}
            </p>
          </div>
        </div>
        <div className="flex flex-wrap items-center gap-2 sm:pl-[52px]">
          <Link
            href={`/doctors/${encodeURIComponent(candidate.slug)}#${REVIEW_ANCHOR}`}
            className={buttonClassName()}
          >
            <Icon name="star" size={20} />
            <span>{t("reviewGrowth.searchPromptWrite")}</span>
          </Link>
          <Button
            type="button"
            variant="white"
            aria-label={tFormat("reviewGrowth.searchPromptLaterLabel", {
              name: candidate.name,
            })}
            onClick={() => {
              skipSearchPrompt("doctor", candidate.slug);
              setCandidate(null);
            }}
          >
            {t("reviewGrowth.searchPromptLater")}
          </Button>
        </div>
      </Card>
    </div>
  );
}
