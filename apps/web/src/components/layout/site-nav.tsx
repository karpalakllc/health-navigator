"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import { cn } from "@/lib/cn";
import { t } from "@/i18n/t";

export const SITE_NAV_LINKS = [
  { href: "/", labelKey: "nav.home" as const },
  { href: "/doctors", labelKey: "nav.doctors" as const },
  { href: "/facilities", labelKey: "nav.facilities" as const },
  { href: "/pharmacies", labelKey: "nav.pharmacies" as const },
  { href: "/products", labelKey: "nav.products" as const },
  { href: "/guidance", labelKey: "nav.guidance" as const },
  { href: "/forum", labelKey: "nav.forum" as const },
] as const;

type SiteNavProps = {
  className?: string;
  onNavigate?: () => void;
  layout?: "horizontal" | "vertical";
};

export function SiteNav({ className, onNavigate, layout = "horizontal" }: SiteNavProps) {
  const pathname = usePathname();
  const vertical = layout === "vertical";

  return (
    <nav
      className={cn(
        "flex flex-wrap items-center gap-1 text-sm",
        vertical && "flex-col flex-nowrap items-stretch",
        className,
      )}
    >
      {SITE_NAV_LINKS.map((link) => {
        const active =
          link.href === "/"
            ? pathname === "/"
            : pathname === link.href || pathname.startsWith(`${link.href}/`);

        return (
          <Link
            key={link.href}
            href={link.href}
            onClick={onNavigate}
            className={cn(
              "inline-flex items-center rounded-lg px-3 font-medium transition",
              vertical ? "w-full py-3" : "h-10 justify-center",
              active
                ? "bg-primary/10 text-primary"
                : "text-muted-foreground hover:bg-secondary hover:text-foreground",
            )}
          >
            {t(link.labelKey)}
          </Link>
        );
      })}
    </nav>
  );
}
