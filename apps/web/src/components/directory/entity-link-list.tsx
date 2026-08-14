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
    return <p className="text-sm text-muted-foreground">{emptyMessage}</p>;
  }

  return (
    <ul className="grid list-none gap-3.5 p-0">
      {items.map((item) => (
        <li key={item.href}>
          <Link
            href={item.href}
            className="grid grid-cols-[52px_1fr] items-start gap-3.5 rounded-[1.375rem] border border-border bg-gradient-to-b from-[#fbfcfc] to-[#f8fafb] p-4 transition hover:shadow-[0_14px_40px_rgb(16_30_36_/_0.08)]"
          >
            <span className="icon-soft-teal flex h-[52px] w-[52px] shrink-0 items-center justify-center rounded-[1.125rem]">
              <BuildingIcon className="h-5 w-5" aria-hidden />
            </span>
            <span className="min-w-0">
              <strong className="block text-base text-foreground">
                {item.title}
              </strong>
              {item.subtitle ? (
                <p className="mt-1.5 text-sm leading-relaxed text-muted-foreground">
                  {item.subtitle}
                </p>
              ) : null}
            </span>
          </Link>
        </li>
      ))}
    </ul>
  );
}

function BuildingIcon({ className }: { className?: string }) {
  return (
    <svg
      className={className}
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      strokeWidth="2"
    >
      <path
        d="M6 22V4a2 2 0 012-2h8a2 2 0 012 2v18M6 12H4M10 12H8M14 12h-2M18 12h-2M10 16H8M14 16h-2M6 20h12"
        strokeLinecap="round"
        strokeLinejoin="round"
      />
    </svg>
  );
}
