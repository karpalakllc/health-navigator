"use client";

import { useEffect, useRef, useState } from "react";
import { DirectoryAvatar } from "@/components/directory/directory-avatar";
import { Icon } from "@/components/ui/icons";
import { Tag } from "@/components/ui/tag";
import { cn } from "@/lib/cn";
import { t } from "@/i18n/t";

type PlaceKind = "facility" | "pharmacy";

/**
 * The cover area of a facility or pharmacy: the API's cover photo
 * (cover_url, WebP up to 1600×900) filling a fixed-aspect box, or — with no
 * cover, or one that fails to load — a soft apricot→cream wash with a faint
 * line icon. Decorative (alt=""): the name is the card's heading. The box's
 * aspect comes from `className`, so the swap never shifts the layout.
 */
export function CoverImage({
  kind,
  coverUrl,
  className,
  loading = "lazy",
}: {
  kind: PlaceKind;
  coverUrl: string | null | undefined;
  className?: string;
  /** "eager" on a profile, where the cover is the first thing on screen. */
  loading?: "lazy" | "eager";
}) {
  const [failedSrc, setFailedSrc] = useState<string | null>(null);
  const imageRef = useRef<HTMLImageElement>(null);
  const showImage = Boolean(coverUrl) && coverUrl !== failedSrc;

  // An error that fired before hydration never reached onError.
  useEffect(() => {
    const image = imageRef.current;
    if (image && image.complete && image.naturalWidth === 0 && image.src) {
      setFailedSrc(image.getAttribute("src"));
    }
  }, [coverUrl]);

  return (
    <div
      data-cover={showImage ? "image" : "placeholder"}
      className={cn(
        "relative overflow-hidden bg-[linear-gradient(135deg,var(--color-apricot)_0%,var(--color-chip-tint)_45%,var(--color-cream)_100%)]",
        className,
      )}
    >
      {showImage ? (
        // Remote media: plain <img> like DirectoryAvatar (next/image cannot
        // be pointed at the API/media host in production; see next.config.ts).
        // eslint-disable-next-line @next/next/no-img-element
        <img
          ref={imageRef}
          src={coverUrl ?? undefined}
          alt=""
          width={1600}
          height={900}
          loading={loading}
          decoding="async"
          fetchPriority={loading === "eager" ? "high" : undefined}
          onError={() => setFailedSrc(coverUrl ?? null)}
          className="absolute inset-0 h-full w-full object-cover"
        />
      ) : (
        <span
          aria-hidden="true"
          className="absolute inset-0 flex items-center justify-center text-ink-2/35"
        >
          <Icon name={kind === "pharmacy" ? "pill" : "building"} size={48} />
        </span>
      )}
    </div>
  );
}

/**
 * The logo slot over a cover's bottom-left corner: a white rounded square
 * with the uploaded logo (avatar_url), else the place's initial. Decorative.
 */
export function CoverLogo({
  kind,
  avatarUrl,
  name,
  size = 56,
  className,
  loading,
}: {
  kind: PlaceKind;
  avatarUrl: string | null;
  name: string;
  size?: 56 | 80;
  className?: string;
  loading?: "lazy" | "eager";
}) {
  return (
    <div
      className={cn("rounded-2xl bg-white p-1 shadow-card", className)}
      data-cover-logo=""
    >
      <DirectoryAvatar
        kind={kind}
        avatarUrl={avatarUrl}
        name={name}
        size={size}
        shape="square"
        loading={loading}
        imageClassName="bg-white object-contain"
        fallbackClassName={size === 80 ? "lg:size-28 lg:text-[2.25rem]" : ""}
        className={size === 80 ? "lg:size-28" : undefined}
      />
    </div>
  );
}

/** „Истакнат“ on a featured card or cover: white pill with a sparkle. */
export function FeaturedMark({ className }: { className?: string }) {
  return (
    <Tag tone="white" icon="sparkle" className={className}>
      {t("ui.featured")}
    </Tag>
  );
}
