"use client";

import { useEffect, useId, useRef, useState } from "react";
import { Icon, type IconName } from "@/components/ui/icons";
import { tagTones, type TagTone } from "@/components/ui/tag-tones";
import { cn } from "@/lib/cn";

/**
 * A label that must explain itself („Истакнат“, „Спонзорирано“): the tag plus
 * a small „i“ toggletip button inside it.
 *
 * - Tap, click, Enter or Space on the button pins the explanation open
 *   (aria-expanded); the same again, Escape, or a press anywhere outside closes
 *   it. Escape returns focus to the button.
 * - A mouse hovering the badge also shows it, without pinning, so desktop
 *   visitors get it without a click. Touch never "hovers" (pointerType check).
 * - The explanation is absolutely positioned under the tag: opening it never
 *   moves the layout. Its container is a polite live region that is empty
 *   while closed, so a screen reader announces the text when it appears.
 */
export function DisclosureBadge({
  label,
  explanation,
  buttonLabel,
  tone = "outline",
  icon,
  className,
}: {
  label: string;
  explanation: string;
  /** Accessible name of the „i“ button, e.g. „Зошто е истакнато?“. */
  buttonLabel: string;
  tone?: TagTone;
  icon?: IconName;
  className?: string;
}) {
  const [pinned, setPinned] = useState(false);
  const [hovered, setHovered] = useState(false);
  const rootRef = useRef<HTMLSpanElement>(null);
  const buttonRef = useRef<HTMLButtonElement>(null);
  const panelId = useId();
  const open = pinned || hovered;

  useEffect(() => {
    if (!open) {
      return;
    }

    function onKeyDown(event: KeyboardEvent) {
      if (event.key !== "Escape") {
        return;
      }
      const hadFocus = rootRef.current?.contains(document.activeElement);
      setPinned(false);
      setHovered(false);
      if (hadFocus) {
        buttonRef.current?.focus();
      }
    }

    function onPointerDown(event: PointerEvent) {
      if (!rootRef.current?.contains(event.target as Node)) {
        setPinned(false);
        setHovered(false);
      }
    }

    document.addEventListener("keydown", onKeyDown);
    document.addEventListener("pointerdown", onPointerDown);

    return () => {
      document.removeEventListener("keydown", onKeyDown);
      document.removeEventListener("pointerdown", onPointerDown);
    };
  }, [open]);

  return (
    <span
      ref={rootRef}
      className={cn("relative inline-flex shrink-0", className)}
      onPointerEnter={(event) => {
        if (event.pointerType === "mouse") {
          setHovered(true);
        }
      }}
      onPointerLeave={(event) => {
        if (event.pointerType === "mouse") {
          setHovered(false);
        }
      }}
    >
      <span className={cn("tag pr-1", tagTones[tone])}>
        {icon ? <Icon name={icon} size={16} /> : null}
        <span>{label}</span>
        <button
          ref={buttonRef}
          type="button"
          aria-label={buttonLabel}
          aria-expanded={pinned}
          aria-controls={panelId}
          onClick={(event) => {
            // A badge can sit inside a clickable card region.
            event.preventDefault();
            event.stopPropagation();
            // Unpinning also drops the hover: a mouse click that closes it
            // must not leave it showing until the pointer moves away.
            if (pinned) {
              setHovered(false);
            }
            setPinned(!pinned);
          }}
          className="inline-flex size-7 shrink-0 cursor-pointer items-center justify-center rounded-full text-ink-2 transition-colors hover:bg-sand hover:text-ink"
        >
          <Icon name="info" size={16} />
        </button>
      </span>
      {/* Always in the tree (an empty live region), so the text is announced
          when it is inserted. */}
      <span
        id={panelId}
        role="status"
        className={cn(
          "absolute left-0 top-full z-50 pt-2",
          !open && "pointer-events-none",
        )}
      >
        {open ? (
          <span className="block w-max max-w-[min(18rem,calc(100vw-2rem))] rounded-2xl border border-line bg-white px-4 py-3 text-left text-[0.9375rem] font-normal leading-[1.375rem] text-ink shadow-card">
            {explanation}
          </span>
        ) : null}
      </span>
    </span>
  );
}
