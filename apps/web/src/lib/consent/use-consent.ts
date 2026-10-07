"use client";

import { useSyncExternalStore } from "react";
import {
  parseConsent,
  readRawConsent,
  subscribeConsent,
  type Consent,
} from "@/lib/consent/consent";

/**
 * The visitor's decision. `undefined` before hydration (server render), then
 * `null` (not decided yet) or the stored choice. Re-renders at once when the
 * choice changes, in this tab or another.
 */
export function useConsent(): Consent | null | undefined {
  const raw = useSyncExternalStore(
    subscribeConsent,
    () => readRawConsent() ?? "",
    () => undefined,
  );

  return raw === undefined ? undefined : parseConsent(raw);
}

/** True only after an explicit „yes“ to statistics. */
export function useStatisticsConsent(): boolean {
  return useConsent()?.statistics === true;
}
