"use client";

import { usePathname } from "next/navigation";
import { useEffect } from "react";
import { redactPageUrl } from "@/lib/analytics/redact-url";

type PlausibleOptions = { u?: string };
type PlausibleFn = ((event: string, options?: PlausibleOptions) => void) & {
  q?: unknown[][];
};

declare global {
  interface Window {
    plausible?: PlausibleFn;
  }
}

/**
 * Sends one pageview per path, with the address redacted to origin + path.
 *
 * Paired with Plausible's manual script, which never reports a pageview by
 * itself. Calls made before the script has loaded go into the queue the
 * script drains on start-up (its documented `plausible.q` convention).
 */
export function PlausiblePageviews() {
  const pathname = usePathname();

  useEffect(() => {
    if (!window.plausible) {
      const queue: PlausibleFn = (...args: unknown[]) => {
        (queue.q = queue.q ?? []).push(args);
      };
      window.plausible = queue;
    }

    const url = redactPageUrl(window.location.href);

    if (url) {
      window.plausible("pageview", { u: url });
    }
  }, [pathname]);

  return null;
}
