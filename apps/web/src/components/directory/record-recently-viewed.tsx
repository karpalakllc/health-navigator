"use client";

import { useEffect } from "react";
import {
  recordRecentlyViewed,
  type RecentlyViewedKind,
} from "@/lib/recently-viewed";

/**
 * Notes a profile visit in this device's „Последно прегледани“ list
 * (localStorage only; see lib/recently-viewed.ts). Renders nothing.
 */
export function RecordRecentlyViewed({
  kind,
  slug,
  name,
  subtitle,
  avatarUrl,
}: {
  kind: RecentlyViewedKind;
  slug: string;
  name: string;
  subtitle?: string | null;
  avatarUrl?: string | null;
}) {
  useEffect(() => {
    recordRecentlyViewed({
      kind,
      slug,
      name,
      subtitle: subtitle ?? null,
      avatarUrl: avatarUrl ?? null,
    });
  }, [kind, slug, name, subtitle, avatarUrl]);

  return null;
}
