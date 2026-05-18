import Link from "next/link";
import { cn } from "@/lib/cn";

export type BreadcrumbItem = {
  label: string;
  href?: string;
};

export function Breadcrumbs({
  items,
  className,
}: {
  items: BreadcrumbItem[];
  className?: string;
}) {
  return (
    <nav
      aria-label="Breadcrumb"
      className={cn("mb-6 flex flex-wrap items-center gap-2.5 text-[0.94rem] text-[#73808c]", className)}
    >
      {items.map((item, index) => {
        const isLast = index === items.length - 1;

        return (
          <span key={`${item.label}-${index}`} className="inline-flex items-center gap-2.5">
            {index > 0 ? (
              <ChevronIcon className="h-4 w-4 shrink-0 text-[#9aa6b2]" aria-hidden />
            ) : null}
            {item.href && !isLast ? (
              <Link href={item.href} className="font-semibold transition hover:text-[#3d4853]">
                {item.label}
              </Link>
            ) : (
              <span className={isLast ? "font-bold text-[#3d4853]" : "font-semibold"}>{item.label}</span>
            )}
          </span>
        );
      })}
    </nav>
  );
}

function ChevronIcon({ className }: { className?: string }) {
  return (
    <svg className={className} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" aria-hidden>
      <path d="M9 6l6 6-6 6" strokeLinecap="round" strokeLinejoin="round" />
    </svg>
  );
}
