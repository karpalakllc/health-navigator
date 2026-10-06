"use client";

import { useEffect, useRef, useState } from "react";
import { useSitePlaceholders } from "@/components/layout/site-placeholders-provider";
import { Monogram, type MonogramSize } from "@/components/ui/user-avatar";
import { cn } from "@/lib/cn";

const SIZE_CLASS: Record<MonogramSize, string> = {
  28: "size-7",
  32: "size-8",
  40: "size-10",
  44: "size-11",
  56: "size-14",
  64: "size-16",
  80: "size-20",
  120: "size-30",
};

type DirectoryAvatarProps = {
  kind: "doctor" | "facility" | "pharmacy";
  avatarUrl: string | null;
  name: string;
  /** Box size in px (D2a: 56 on cards, 80 mobile / 120 desktop profiles). */
  size?: MonogramSize;
  /** circle (doctors) or rounded square (a facility's / pharmacy's logo). */
  shape?: "circle" | "square";
  /**
   * The photo's alt text. A doctor's photo is named after the doctor; a
   * logo next to its own name stays decorative ("").
   */
  alt?: string;
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
 * facility's or pharmacy's first letter. A photo that fails to load (gone
 * from storage, blocked) falls back to the same disc instead of a broken
 * image. The box has a fixed size and aspect, so the swap never shifts the
 * layout.
 */
export function DirectoryAvatar({
  kind,
  avatarUrl,
  name,
  size = 56,
  shape = "circle",
  alt = "",
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
  const [failedSrc, setFailedSrc] = useState<string | null>(null);
  const imageRef = useRef<HTMLImageElement>(null);

  // An error that fired before hydration never reached onError: catch an
  // image that has already finished loading with nothing to show.
  useEffect(() => {
    const image = imageRef.current;
    if (image && image.complete && image.naturalWidth === 0 && image.src) {
      setFailedSrc(image.getAttribute("src"));
    }
  }, [src]);

  if (src && src !== failedSrc) {
    return (
      <div
        className={cn(
          "relative shrink-0 overflow-hidden",
          shape === "square" ? "rounded-xl" : "rounded-full",
          tone === "white" ? "bg-white" : "bg-sand",
          SIZE_CLASS[size],
          className,
        )}
      >
        {/* Remote admin-uploaded avatar; next/image would 400 in production because
            images.remotePatterns cannot read NEXT_PUBLIC_API_URL. See next.config.ts. */}
        {/* eslint-disable-next-line @next/next/no-img-element */}
        <img
          ref={imageRef}
          src={src}
          alt={alt}
          width={size}
          height={size}
          loading={loading}
          decoding="async"
          onError={() => setFailedSrc(src)}
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
      shape={shape}
      className={cn(fallbackClassName, className)}
    />
  );
}
