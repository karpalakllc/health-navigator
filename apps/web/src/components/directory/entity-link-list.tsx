import Link from "next/link";
import type { ReactNode } from "react";
import { Icon, type IconName } from "@/components/ui/icons";

export type EntityLinkItem = {
  href: string;
  title: string;
  subtitle?: string;
  /** Right-hand value, e.g. a price. */
  aside?: ReactNode;
  /** A leading node instead of the icon disc (e.g. a monogram). */
  leading?: ReactNode;
};

type EntityLinkListProps = {
  items: EntityLinkItem[];
  emptyMessage: string;
  icon?: IconName;
  /** Two columns from md up (e.g. a facility's doctors). */
  columns?: 1 | 2;
};

/** Sand rows linking to related profiles: icon disc, title, meta, chevron. */
export function EntityLinkList({
  items,
  emptyMessage,
  icon = "building",
  columns = 1,
}: EntityLinkListProps) {
  if (items.length === 0) {
    return <p className="type-body text-ink-2">{emptyMessage}</p>;
  }

  return (
    <ul
      className={
        columns === 2
          ? "m-0 grid list-none gap-2 p-0 md:grid-cols-2"
          : "m-0 grid list-none gap-2 p-0"
      }
    >
      {items.map((item) => (
        <li key={item.href}>
          <Link
            href={item.href}
            className="flex min-h-16 items-center gap-3 rounded-2xl bg-sand/60 px-3 py-2.5 text-ink no-underline hover:bg-sand"
          >
            {item.leading ?? (
              <span className="inline-flex size-11 shrink-0 items-center justify-center rounded-full bg-white">
                <Icon name={icon} size={22} />
              </span>
            )}
            <span className="flex min-w-0 flex-1 flex-col">
              <span className="type-body font-semibold">{item.title}</span>
              {item.subtitle ? (
                <span className="type-meta text-ink-2">{item.subtitle}</span>
              ) : null}
            </span>
            {item.aside ? (
              <span className="shrink-0 text-right">{item.aside}</span>
            ) : null}
            <Icon name="chevron-right" size={20} className="text-ink-2" />
          </Link>
        </li>
      ))}
    </ul>
  );
}
