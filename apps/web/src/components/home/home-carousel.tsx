"use client";

import {
  Children,
  useCallback,
  useEffect,
  useId,
  useRef,
  useState,
  type ReactNode,
} from "react";
import { IconButton } from "@/components/ui/button";
import { cn } from "@/lib/cn";
import { t, tFormat } from "@/i18n/t";

type Visible = { first: number; last: number };

/** A card counts as shown when it is inside the rail give or take 2px. */
const TOLERANCE = 2;

function prefersReducedMotion() {
  return (
    typeof window.matchMedia === "function" &&
    window.matchMedia("(prefers-reduced-motion: reduce)").matches
  );
}

/** Which cards are fully inside the scroller right now. */
export function measureVisible(list: HTMLElement): Visible {
  const items = Array.from(list.children) as HTMLElement[];
  const left = list.scrollLeft;
  const right = left + list.clientWidth;
  let first = -1;
  let last = -1;
  items.forEach((item, index) => {
    const start = item.offsetLeft;
    const end = start + item.offsetWidth;
    if (start >= left - TOLERANCE && end <= right + TOLERANCE) {
      if (first === -1) first = index;
      last = index;
    }
  });
  if (first === -1) {
    // A card wider than the rail: the one nearest the start is „shown“.
    const nearest = items.findIndex(
      (item) => item.offsetLeft + item.offsetWidth > left + TOLERANCE,
    );
    first = Math.max(nearest, 0);
    last = first;
  }
  return { first, last };
}

/**
 * A horizontal card rail with a position line („1–3 од 6“), dots and, from
 * md, previous/next buttons. Swiping and the trackpad scroll it natively;
 * it snaps card by card (each <li> child carries `snap-start`), the next
 * card peeks in, and the padding inside the scroller keeps card shadows and
 * focus rings whole. The buttons page by the visible width — instantly when
 * the visitor prefers reduced motion — and are disabled at either end. When
 * every card fits, the controls are left out.
 *
 * Children are the rail's <li> items; `listClassName` lays out the <ul>.
 * The section around it supplies the name (a heading).
 */
export function HomeCarousel({
  children,
  listClassName,
  controlsClassName,
}: {
  children: ReactNode;
  listClassName?: string;
  controlsClassName?: string;
}) {
  const listId = useId();
  const listRef = useRef<HTMLUListElement>(null);
  const frame = useRef(0);
  const total = Children.count(children);
  // Server render: unknown widths, so the first card is „shown“ and the
  // controls start out as if there were more (no flash of missing arrows).
  const [visible, setVisible] = useState<Visible>({ first: 0, last: 0 });

  const update = useCallback(() => {
    const list = listRef.current;
    if (!list) return;
    const next = measureVisible(list);
    setVisible((prev) =>
      prev.first === next.first && prev.last === next.last ? prev : next,
    );
  }, []);

  const schedule = useCallback(() => {
    cancelAnimationFrame(frame.current);
    frame.current = requestAnimationFrame(update);
  }, [update]);

  useEffect(() => {
    update();
    window.addEventListener("resize", schedule);
    return () => {
      window.removeEventListener("resize", schedule);
      cancelAnimationFrame(frame.current);
    };
  }, [update, schedule]);

  function page(direction: 1 | -1) {
    const list = listRef.current;
    if (!list) return;
    const items = Array.from(list.children) as HTMLElement[];
    const { first, last } = measureVisible(list);
    const span = Math.max(last - first + 1, 1);
    const target =
      items[Math.min(Math.max(first + direction * span, 0), items.length - 1)];
    const padding = parseFloat(getComputedStyle(list).scrollPaddingLeft) || 0;
    list.scrollTo({
      left: target ? target.offsetLeft - padding : 0,
      behavior: prefersReducedMotion() ? "auto" : "smooth",
    });
  }

  const atStart = visible.first <= 0;
  const atEnd = visible.last >= total - 1;
  const position =
    visible.first === visible.last
      ? tFormat("homeSearch.railPositionOne", {
          from: visible.first + 1,
          total,
        })
      : tFormat("homeSearch.railPosition", {
          from: visible.first + 1,
          to: visible.last + 1,
          total,
        });

  return (
    <div>
      <ul
        ref={listRef}
        id={listId}
        data-rail=""
        onScroll={schedule}
        className={cn("scroll-row relative overflow-x-auto", listClassName)}
      >
        {children}
      </ul>
      {total > 1 && !(atStart && atEnd) ? (
        <div
          data-rail-controls=""
          className={cn("flex items-center gap-3", controlsClassName)}
        >
          <span aria-hidden="true" className="flex items-center gap-1.5">
            {Array.from({ length: total }, (_, index) => (
              <span
                key={index}
                data-rail-dot={
                  index >= visible.first && index <= visible.last ? "on" : "off"
                }
                className={cn(
                  "h-2 rounded-full transition-[width,background-color] duration-[var(--duration-base)]",
                  index >= visible.first && index <= visible.last
                    ? "w-5 bg-ink"
                    : "w-2 bg-line-strong/50",
                )}
              />
            ))}
          </span>
          <p
            className="type-meta font-semibold text-ink-2"
            data-rail-position=""
          >
            {position}
          </p>
          <span className="ml-auto hidden gap-2 md:flex">
            <IconButton
              icon="arrow-left"
              label={t("homeSearch.railPrev")}
              variant="secondary"
              size={44}
              aria-controls={listId}
              disabled={atStart}
              onClick={() => page(-1)}
            />
            <IconButton
              icon="arrow-right"
              label={t("homeSearch.railNext")}
              variant="secondary"
              size={44}
              aria-controls={listId}
              disabled={atEnd}
              onClick={() => page(1)}
            />
          </span>
        </div>
      ) : null}
    </div>
  );
}
