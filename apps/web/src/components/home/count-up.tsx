"use client";

import { useEffect, useRef, useState } from "react";

const DURATION_MS = 700;

/**
 * A number that counts up once, the first time it scrolls into view.
 * The server HTML (and a no-JS, crawler, reduced-motion or already-visible
 * render) shows the final value; only a number still below the fold at
 * hydration is reset to 0 and then counted up, so nothing visibly jumps.
 * The moving digits are aria-hidden; assistive technology reads the final
 * value from a visually hidden copy.
 */
export function CountUp({
  value,
  className,
}: {
  value: number;
  className?: string;
}) {
  const ref = useRef<HTMLSpanElement>(null);
  const [shown, setShown] = useState(value);

  useEffect(() => {
    const el = ref.current;
    if (
      !el ||
      value <= 0 ||
      typeof IntersectionObserver === "undefined" ||
      (typeof window.matchMedia === "function" &&
        window.matchMedia("(prefers-reduced-motion: reduce)").matches)
    ) {
      return;
    }
    if (el.getBoundingClientRect().top < window.innerHeight) {
      return;
    }

    let frame = 0;
    // Below the fold: start from zero (out of sight, so no jump is seen).
    setShown(0);
    const observer = new IntersectionObserver(
      (entries) => {
        if (!entries.some((entry) => entry.isIntersecting)) return;
        observer.disconnect();
        const start = performance.now();
        const tick = (now: number) => {
          const progress = Math.min((now - start) / DURATION_MS, 1);
          const eased = 1 - (1 - progress) ** 3;
          setShown(Math.round(value * eased));
          if (progress < 1) frame = requestAnimationFrame(tick);
        };
        frame = requestAnimationFrame(tick);
      },
      { threshold: 0.6 },
    );
    observer.observe(el);

    return () => {
      observer.disconnect();
      cancelAnimationFrame(frame);
      setShown(value);
    };
  }, [value]);

  return (
    <span className={className}>
      <span ref={ref} aria-hidden="true" className="tabular-nums">
        {shown}
      </span>
      <span className="sr-only">{value}</span>
    </span>
  );
}
