import Link from "next/link";
import type { ReactNode } from "react";

type EntityListProps = {
  emptyMessage: string;
  isEmpty: boolean;
  children: ReactNode;
};

export function EntityList({
  emptyMessage,
  isEmpty,
  children,
}: EntityListProps) {
  return (
    <ul className="divide-y divide-zinc-200 rounded-lg border border-zinc-200 bg-white">
      {isEmpty ? (
        <li className="p-4 text-sm text-zinc-500">{emptyMessage}</li>
      ) : (
        children
      )}
    </ul>
  );
}

export function EntityListItem({
  href,
  title,
  subtitle,
}: {
  href: string;
  title: string;
  subtitle?: string;
}) {
  return (
    <li>
      <Link href={href} className="block p-4 hover:bg-zinc-50">
        <p className="font-medium text-zinc-900">{title}</p>
        {subtitle ? <p className="text-sm text-zinc-600">{subtitle}</p> : null}
      </Link>
    </li>
  );
}
