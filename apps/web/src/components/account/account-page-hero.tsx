import type { ReactNode } from "react";
import { DirectoryHero } from "@/components/design/directory-hero";

type AccountPageHeroProps = {
  badge: ReactNode;
  title: string;
  description: string;
};

export function AccountPageHero({
  badge,
  title,
  description,
}: AccountPageHeroProps) {
  return (
    <DirectoryHero badge={badge} title={title} description={description} />
  );
}
