"use client";

import {
  useEffect,
  useId,
  useRef,
  useState,
  type ReactNode,
  type RefObject,
} from "react";
import { createPortal } from "react-dom";
import { IconButton } from "@/components/ui/button";
import { cn } from "@/lib/cn";
import { t } from "@/i18n/t";

const FOCUSABLE =
  'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

type BottomSheetProps = {
  open: boolean;
  onClose: () => void;
  title: ReactNode;
  children: ReactNode;
  /** Pinned under the scrolling body (e.g. „Исчисти“ + „Прикажи N резултати“). */
  footer?: ReactNode;
  /** Where focus goes on open; the first focusable control by default. */
  initialFocusRef?: RefObject<HTMLElement | null>;
  /** Accessible name of the × button; „Затвори“ by default. */
  closeLabel?: string;
  className?: string;
};

/**
 * A full-height sheet that slides up from the bottom on mobile (radius-28 top
 * corners, the sheet shadow, a grab handle). It is a modal dialog: focus moves
 * in on open, Tab/Shift+Tab stay inside, Escape and the backdrop close it,
 * focus returns to the opener, the page behind does not scroll (iOS
 * included) and is inert, so neither a screen reader's virtual cursor nor a
 * stray tap can reach it.
 *
 * It renders into document.body, above the bottom tab bar.
 */
export function BottomSheet({
  open,
  onClose,
  title,
  children,
  footer,
  initialFocusRef,
  closeLabel,
  className,
}: BottomSheetProps) {
  const panelRef = useRef<HTMLDivElement>(null);
  const portalRef = useRef<HTMLDivElement>(null);
  const titleId = useId();
  const [shown, setShown] = useState(false);
  const onCloseRef = useRef(onClose);

  useEffect(() => {
    onCloseRef.current = onClose;
  }, [onClose]);

  useEffect(() => {
    if (!open) {
      return;
    }

    const opener =
      document.activeElement instanceof HTMLElement
        ? document.activeElement
        : null;
    const focusables = () =>
      Array.from(
        panelRef.current?.querySelectorAll<HTMLElement>(FOCUSABLE) ?? [],
      ).filter((el) => !el.closest("[inert]"));

    const restoreScroll = lockBodyScroll();
    const restoreInert = makeRestInert(portalRef.current);

    function onKeyDown(event: KeyboardEvent) {
      if (event.key === "Escape") {
        event.preventDefault();
        onCloseRef.current();
        return;
      }

      if (event.key !== "Tab") {
        return;
      }

      const items = focusables();
      if (items.length === 0) {
        event.preventDefault();
        return;
      }

      const first = items[0];
      const last = items[items.length - 1];
      const active = document.activeElement;
      const inside = panelRef.current?.contains(active) ?? false;

      if (event.shiftKey && (active === first || !inside)) {
        event.preventDefault();
        last.focus();
      } else if (!event.shiftKey && (active === last || !inside)) {
        event.preventDefault();
        first.focus();
      }
    }

    document.addEventListener("keydown", onKeyDown);
    (initialFocusRef?.current ?? focusables()[0])?.focus();
    // Next frame: slide in (the transition only runs with motion allowed).
    const frame = requestAnimationFrame(() => setShown(true));

    return () => {
      cancelAnimationFrame(frame);
      setShown(false);
      document.removeEventListener("keydown", onKeyDown);
      restoreInert();
      restoreScroll();
      if (opener?.isConnected) {
        opener.focus();
      }
    };
  }, [open, initialFocusRef]);

  if (!open || typeof document === "undefined") {
    return null;
  }

  return createPortal(
    <div ref={portalRef} className="fixed inset-0 z-[200]">
      <div
        aria-hidden="true"
        className="absolute inset-0 bg-ink/50"
        onClick={onClose}
      />
      <div
        ref={panelRef}
        role="dialog"
        aria-modal="true"
        aria-labelledby={titleId}
        className={cn(
          "absolute inset-x-0 bottom-0 top-3 flex flex-col overflow-hidden rounded-t-sheet bg-white shadow-sheet",
          "motion-safe:transition-transform motion-safe:duration-[240ms] motion-safe:ease-out",
          shown ? "translate-y-0" : "motion-safe:translate-y-full",
          "lg:inset-x-auto lg:top-0 lg:right-0 lg:w-[28rem] lg:rounded-t-none lg:rounded-l-sheet",
          className,
        )}
      >
        <div className="flex shrink-0 justify-center pt-2" aria-hidden="true">
          <span className="h-1 w-10 rounded-full bg-sand" />
        </div>
        <div className="flex shrink-0 items-center justify-between gap-3 pb-2 pl-5 pr-3 pt-1">
          <h2 id={titleId} className="type-h2 text-ink">
            {title}
          </h2>
          <IconButton
            icon="x"
            label={closeLabel ?? t("search.close")}
            variant="soft"
            onClick={onClose}
          />
        </div>
        <div className="min-h-0 flex-1 overflow-y-auto overscroll-contain px-5 pb-6">
          {children}
        </div>
        {footer ? (
          <div className="shrink-0 border-t border-line bg-white px-5 pb-[calc(0.75rem+env(safe-area-inset-bottom,0px))] pt-3">
            {footer}
          </div>
        ) : null}
      </div>
    </div>,
    document.body,
  );
}

/**
 * Locks the page behind a modal. `overflow: hidden` alone doesn't stop iOS
 * Safari from scrolling the body under a touch, so the body is pinned with
 * `position: fixed` at the current offset and the scroll position is put
 * back on release.
 */
function lockBodyScroll(): () => void {
  const { body } = document;
  const scrollY = window.scrollY;
  const previous = {
    overflow: body.style.overflow,
    position: body.style.position,
    top: body.style.top,
    width: body.style.width,
  };

  body.style.overflow = "hidden";
  body.style.position = "fixed";
  body.style.top = `-${scrollY}px`;
  body.style.width = "100%";

  return () => {
    Object.assign(body.style, previous);
    if (scrollY > 0) {
      window.scrollTo({ top: scrollY, left: 0, behavior: "instant" });
    }
  };
}

/**
 * Marks every other child of <body> inert (aria-modal alone isn't honoured
 * by every screen reader, and it never stops pointer input). Elements that
 * were already inert are left as they were.
 */
function makeRestInert(keep: HTMLElement | null): () => void {
  const changed = Array.from(document.body.children).filter(
    (el) => el !== keep && !el.hasAttribute("inert"),
  );

  for (const el of changed) {
    el.setAttribute("inert", "");
  }

  return () => {
    for (const el of changed) {
      el.removeAttribute("inert");
    }
  };
}
