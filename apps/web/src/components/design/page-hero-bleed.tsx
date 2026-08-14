import type { ReactNode } from "react";
import { cn } from "@/lib/cn";
import { pageContainerClass } from "@/components/ui/layout";

type PageHeroBleedProps = {
  children: ReactNode;
  className?: string;
  innerClassName?: string;
};

/** Full-viewport ambient shadow; hero content aligned to site header width. */
export function PageHeroBleed({
  children,
  className,
  innerClassName,
}: PageHeroBleedProps) {
  return (
    <section className={cn("relative w-full pt-[18px] pb-2", className)}>
      <div
        className="pointer-events-none absolute left-1/2 top-0 -z-10 h-full min-h-[260px] w-screen max-w-[100vw] -translate-x-1/2"
        aria-hidden
      >
        <div className="absolute inset-x-0 top-6 mx-auto h-56 max-w-[min(92vw,1280px)] rounded-[2.75rem] bg-[rgb(16_30_36_/_0.07)] blur-3xl" />
        <div className="absolute inset-x-0 top-10 mx-auto h-48 max-w-[min(88vw,1240px)] rounded-[2.125rem] border border-white/50 bg-white/25 shadow-[0_28px_90px_rgb(16_30_36_/_0.1)]" />
      </div>
      <div
        className={cn(
          pageContainerClass,
          "relative flex flex-col gap-6",
          innerClassName,
        )}
      >
        {children}
      </div>
    </section>
  );
}
