import type { ReactNode } from "react";
import { HeroMeshCard } from "@/components/design/hero-mesh-card";

type DirectoryHeroProps = {
  badge: ReactNode;
  title: string;
  description: string;
  stat?: ReactNode;
  filters?: ReactNode;
};

export function DirectoryHero({
  badge,
  title,
  description,
  stat,
  filters,
}: DirectoryHeroProps) {
  return (
    <HeroMeshCard
      variant="directory"
      align="start"
      innerClassName="w-full max-w-none space-y-6"
    >
      <div className="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
        <div className="max-w-3xl space-y-4">
          <div className="inline-flex items-center gap-2.5 rounded-full border border-white/90 bg-white/90 px-4 py-2 text-sm font-semibold text-[#5c6670] shadow-[0_14px_40px_rgb(16_30_36_/_0.07)]">
            {badge}
          </div>
          <h1 className="text-4xl font-black tracking-tight text-foreground sm:text-5xl lg:text-[4rem] lg:leading-[0.95]">
            {title}
          </h1>
          <p className="text-lg leading-relaxed text-muted-foreground">
            {description}
          </p>
        </div>
        {stat ? <div className="shrink-0">{stat}</div> : null}
      </div>
      {filters ?? null}
    </HeroMeshCard>
  );
}
