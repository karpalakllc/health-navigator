"use client";

import type { ReactNode } from "react";
import { useStatisticsConsent } from "@/lib/consent/use-consent";

/** Renders its children only while the visitor has accepted statistics. */
export function StatisticsOnly({ children }: { children: ReactNode }) {
  return useStatisticsConsent() ? <>{children}</> : null;
}
