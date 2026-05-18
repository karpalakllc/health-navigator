import type { ReactNode } from "react";
import { cn } from "@/lib/cn";

type HeroMeshCardProps = {
  children: ReactNode;
  className?: string;
  innerClassName?: string;
  align?: "center" | "start" | "full";
  /** Home uses a stronger hero shell; directory/profile use tighter panels. */
  variant?: "home" | "directory" | "profile";
};

const shellClass: Record<NonNullable<HeroMeshCardProps["variant"]>, string> = {
  home: "surface-glass-strong rounded-[1.75rem] border border-white/[0.82] bg-white/[0.78] px-5 py-7 shadow-[0_28px_90px_rgb(16_30_36_/_0.12)] sm:rounded-[2.125rem] sm:px-[34px] sm:py-[38px]",
  directory: "directory-hero-panel px-5 py-7 sm:px-[34px] sm:py-[38px]",
  profile: "profile-hero-panel p-7",
};

export function HeroMeshCard({
  children,
  className,
  innerClassName,
  align = "center",
  variant = "home",
}: HeroMeshCardProps) {
  return (
    <div className={cn("relative block w-full max-w-none", shellClass[variant], className)}>
      <div className="hero-mesh-grid pointer-events-none absolute inset-0" aria-hidden />
      <div
        className={cn(
          "hero-blob-red pointer-events-none absolute rounded-full blur-[68px]",
          variant === "directory"
            ? "-left-[60px] top-2.5 h-[320px] w-[320px] opacity-[0.27]"
            : "-left-16 top-5 h-[340px] w-[340px] opacity-30",
        )}
        aria-hidden
      />
      <div
        className={cn(
          "hero-blob-teal pointer-events-none absolute rounded-full blur-[68px]",
          variant === "directory"
            ? "-right-20 -top-2.5 h-[360px] w-[360px] opacity-[0.27]"
            : "-right-20 -top-2 h-[400px] w-[400px] opacity-30",
        )}
        aria-hidden
      />
      <div
        className={cn(
          "relative w-full",
          align === "center" && "mx-auto max-w-[820px] text-center",
          align === "full" && "text-center",
          align === "start" && "text-left",
          innerClassName,
        )}
      >
        {children}
      </div>
    </div>
  );
}
