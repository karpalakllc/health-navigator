"use client";

import { useSitePlaceholders } from "@/components/layout/site-placeholders-provider";
import { cn } from "@/lib/cn";

type DirectoryAvatarProps = {
  kind: "doctor" | "facility" | "pharmacy";
  avatarUrl: string | null;
  name: string;
  className?: string;
  imageClassName?: string;
  fallbackClassName?: string;
};

export function DirectoryAvatar({
  kind,
  avatarUrl,
  name,
  className,
  imageClassName,
  fallbackClassName,
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
      <img
        src={src}
        alt=""
        className={cn("object-cover", imageClassName, className)}
      />
    );
  }

  return (
    <div
      className={cn(
        "flex items-center justify-center font-bold",
        kind === "doctor" && "bg-primary/10 text-primary",
        kind === "facility" && "bg-accent/10 text-accent",
        kind === "pharmacy" && "bg-teal-500/10 text-teal-700",
        fallbackClassName,
        className,
      )}
      aria-hidden
    >
      {name.charAt(0)}
    </div>
  );
}
