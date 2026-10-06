"use client";

import { useState, useSyncExternalStore } from "react";
import { OpenStatusLine } from "@/components/directory/open-status";

const subscribe = () => () => {};

/**
 * OpenStatusLine for client components. It renders nothing on the server and
 * during hydration, then the status from the browser's clock: "open now"
 * depends on the minute, so a server answer could disagree with the hydrating
 * client and break hydration (React #418) instead of just showing old text.
 */
export function LiveOpenStatusLine({
  hours,
  className,
}: {
  hours: Record<string, string> | unknown[] | null | undefined;
  className?: string;
}) {
  const mounted = useSyncExternalStore(
    subscribe,
    () => true,
    () => false,
  );
  const [now] = useState(() => new Date());

  if (!mounted) {
    return null;
  }

  return <OpenStatusLine hours={hours} now={now} className={className} />;
}
