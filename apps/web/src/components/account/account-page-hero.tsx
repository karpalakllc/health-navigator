import type { ReactNode } from "react";

type AccountPageHeroProps = {
  badge: ReactNode;
  title: string;
  description: string;
};

/**
 * The account pages' header: a short eyebrow, the h1 and one line. Plain on
 * the cream page — no hero card — so the content starts high on a phone.
 */
export function AccountPageHero({
  badge,
  title,
  description,
}: AccountPageHeroProps) {
  return (
    <header className="flex flex-col gap-2">
      <p className="type-meta font-semibold text-ink-2">{badge}</p>
      <h1 className="type-h1 text-ink">{title}</h1>
      <p className="type-body text-ink-2 measure">{description}</p>
    </header>
  );
}
