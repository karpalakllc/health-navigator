import Image from "next/image";
import { cn } from "@/lib/cn";
import { doctorInitials, initialsFromName } from "@/lib/user-initials";

/*
 * Avatars are neutral initials discs: sand fill, ink Geologica 600 (13.3:1).
 * Initials come from the person's *name*, never from a title — doctors go
 * through doctorInitials() so „д-р Марија Петровска“ is „МП“, not „Д“.
 */
const SIZES = {
  28: "size-7 text-[0.875rem]",
  32: "size-8 text-[0.875rem]",
  40: "size-10 text-[0.9375rem]",
  44: "size-11 text-base",
  56: "size-14 text-xl",
  80: "size-20 text-[1.75rem]",
  120: "size-30 text-[2.5rem]",
} as const;

export type MonogramSize = keyof typeof SIZES;

export function monogramInitials(
  name: string,
  kind: "person" | "doctor" = "person",
): string {
  return kind === "doctor" ? doctorInitials(name) : initialsFromName(name);
}

/** Initials disc. Decorative (aria-hidden): the name is always shown beside it. */
export function Monogram({
  name,
  initials,
  kind = "person",
  size = 40,
  tone = "sand",
  className,
}: {
  name: string;
  /** Use the API's precomputed initials when it sends them. */
  initials?: string;
  kind?: "person" | "doctor";
  size?: MonogramSize;
  /** sand on white/cream surfaces; white on sand/apricot surfaces. */
  tone?: "sand" | "white";
  className?: string;
}) {
  return (
    <span
      aria-hidden="true"
      className={cn(
        "inline-flex shrink-0 items-center justify-center rounded-full font-semibold leading-none tracking-[0.01em] text-ink",
        tone === "white" ? "bg-white" : "bg-sand",
        SIZES[size],
        className,
      )}
    >
      {initials ?? monogramInitials(name, kind)}
    </span>
  );
}

type UserAvatarProps = {
  name: string;
  avatarUrl?: string | null;
  initials?: string;
  className?: string;
  size?: "sm" | "md" | "lg";
};

const LEGACY_SIZE = { sm: 40, md: 56, lg: 80 } as const;

/** A member's photo, or their Monogram when they have none. */
export function UserAvatar({
  name,
  avatarUrl,
  initials,
  className,
  size = "sm",
}: UserAvatarProps) {
  const px = LEGACY_SIZE[size];

  if (avatarUrl) {
    return (
      <span
        className={cn(
          "relative inline-flex shrink-0 overflow-hidden rounded-full bg-sand",
          SIZES[px],
          className,
        )}
      >
        <Image
          src={avatarUrl}
          alt={name}
          width={px}
          height={px}
          className="h-full w-full object-cover"
          unoptimized
        />
      </span>
    );
  }

  return (
    <Monogram name={name} initials={initials} size={px} className={className} />
  );
}
