"use client";

import { useSitePlaceholders } from "@/components/layout/site-placeholders-provider";
import { Monogram, type MonogramSize } from "@/components/ui/user-avatar";
import { cn } from "@/lib/cn";

const SIZE_CLASS: Record<MonogramSize, string> = {
  28: "size-7",
  32: "size-8",
  40: "size-10",
  44: "size-11",
  56: "size-14",
  80: "size-20",
  120: "size-30",
};

type DirectoryAvatarProps = {
  kind: "doctor" | "facility" | "pharmacy";
  avatarUrl: string | null;
  name: string;
  /** Disc size in px (D2a: 56 on cards, 80 mobile / 120 desktop profiles). */
  size?: MonogramSize;
  className?: string;
  imageClassName?: string;
  fallbackClassName?: string;
  /** Pass "eager" where the avatar is above the fold (a profile hero). */
  loading?: "lazy" | "eager";
  /** White disc on sand/apricot surfaces. */
  tone?: "sand" | "white";
};

/**
 * An uploaded photo (or the admin's placeholder image), else a neutral
 * initials disc: a doctor's from their name without the „д-р“ title, a
 * facility's or pharmacy's first letter. Decorative — the name is always
 * shown beside it.
 */
export function DirectoryAvatar({
  kind,
  avatarUrl,
  name,
  size = 56,
  className,
  imageClassName,
  fallbackClassName,
  loading = "lazy",
  tone = "sand",
}: DirectoryAvatarProps) {
  const placeholders = useSitePlaceholders();
  const placeholder =
    kind === "doctor"
      ? placeholders.doctor
      : kind === "pharmacy"
        ? placeholders.pharmacy
        : placeholders.facility;
  const src = avatarUrl ?? placeholder;

  if (src) {
    return (
      <div
        className={cn(
          "relative shrink-0 overflow-hidden rounded-full bg-sand",
          SIZE_CLASS[size],
          className,
        )}
      >
        {/* Remote admin-uploaded avatar; next/image would 400 in production because
            images.remotePatterns cannot read NEXT_PUBLIC_API_URL. See next.config.ts. */}
        {/* eslint-disable-next-line @next/next/no-img-element */}
        <img
          src={src}
          alt=""
          loading={loading}
          decoding="async"
          className={cn(
            "h-full w-full object-cover object-center",
            imageClassName,
          )}
        />
      </div>
    );
  }

  return (
    <Monogram
      name={name}
      kind={kind === "doctor" ? "doctor" : "person"}
      initials={kind === "doctor" ? undefined : name.trim().charAt(0)}
      size={size}
      tone={tone}
      className={cn(fallbackClassName, className)}
    />
  );
}
