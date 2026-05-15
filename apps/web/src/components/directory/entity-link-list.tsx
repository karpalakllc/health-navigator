import Link from "next/link";

export type EntityLinkItem = {
  href: string;
  title: string;
  subtitle?: string;
};

type EntityLinkListProps = {
  items: EntityLinkItem[];
  emptyMessage: string;
};

export function EntityLinkList({ items, emptyMessage }: EntityLinkListProps) {
  if (items.length === 0) {
    return <p className="text-sm text-zinc-500">{emptyMessage}</p>;
  }

  return (
    <ul className="divide-y divide-zinc-200 rounded-lg border border-zinc-200 bg-white">
      {items.map((item) => (
        <li key={item.href}>
          <Link href={item.href} className="block p-4 hover:bg-zinc-50">
            <p className="font-medium text-zinc-900">{item.title}</p>
            {item.subtitle ? (
              <p className="text-sm text-zinc-600">{item.subtitle}</p>
            ) : null}
          </Link>
        </li>
      ))}
    </ul>
  );
}
