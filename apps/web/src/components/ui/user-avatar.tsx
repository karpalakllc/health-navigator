import Image from "next/image";
import { cn } from "@/lib/cn";
import { initialsFromName } from "@/lib/user-initials";

type UserAvatarProps = {
  name: string;
  avatarUrl?: string | null;
  initials?: string;
  className?: string;
  size?: "sm" | "md" | "lg";
};

const sizeClasses = {
  sm: "h-10 w-10 text-sm",
  md: "h-14 w-14 text-base",
  lg: "h-20 w-20 text-xl",
};

export function UserAvatar({
  name,
  avatarUrl,
  initials,
  className,
  size = "sm",
}: UserAvatarProps) {
  const label = initials ?? initialsFromName(name);
  const dimension = size === "lg" ? 80 : size === "md" ? 56 : 40;

  if (avatarUrl) {
    return (
      <span
        className={cn(
          "relative inline-flex shrink-0 overflow-hidden rounded-xl border border-border bg-card shadow-sm",
          sizeClasses[size],
          className,
        )}
      >
        <Image
          src={avatarUrl}
          alt={name}
          width={dimension}
          height={dimension}
          className="h-full w-full object-cover"
          unoptimized
        />
      </span>
    );
  }

  return (
    <span
      className={cn(
        "inline-flex shrink-0 items-center justify-center rounded-xl border border-border bg-gradient-to-br from-primary/90 to-accent font-bold text-primary-foreground shadow-sm",
        sizeClasses[size],
        className,
      )}
      aria-hidden={!name}
    >
      {label}
    </span>
  );
}
