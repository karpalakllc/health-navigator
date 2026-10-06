"use client";

import { useSitePlaceholders } from "@/components/layout/site-placeholders-provider";
import { cn } from "@/lib/cn";
import { doctorInitials } from "@/lib/user-initials";

type DirectoryAvatarProps = {
  kind: "doctor" | "facility" | "pharmacy";
  avatarUrl: string | null;
  name: string;
  className?: string;
  imageClassName?: string;
  fallbackClassName?: string;
  /** Pass "eager" where the avatar is above the fold (a profile hero). */
  loading?: "lazy" | "eager";
};

export function DirectoryAvatar({
  kind,
  avatarUrl,
  name,
  className,
  imageClassName,
  fallbackClassName,
  loading = "lazy",
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
          "relative shrink-0 overflow-hidden bg-[#eef2f4]",
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
    <div
      className={cn(
        "flex shrink-0 items-center justify-center font-bold",
        kind === "doctor" && "bg-primary/10 text-primary",
        kind === "facility" && "bg-accent/10 text-accent",
        kind === "pharmacy" && "bg-teal-500/10 text-teal-700",
        fallbackClassName,
        className,
      )}
      aria-hidden
    >
      {kind === "doctor" ? doctorInitials(name) : name.charAt(0)}
    </div>
  );
}
