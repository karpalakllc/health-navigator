"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import { cn } from "@/lib/cn";
import { isPathEnabled, type ModuleFlags } from "@/lib/site-modules";
import { t } from "@/i18n/t";

export const SITE_NAV_LINKS = [
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
  /** Switched-off modules are left out rather than linking to a dead end. */
  modules: ModuleFlags;
};

export function SiteNav({
  className,
  onNavigate,
  layout = "horizontal",
  modules,
}: SiteNavProps) {
  const pathname = usePathname();
  const vertical = layout === "vertical";
  const links = SITE_NAV_LINKS.filter((link) =>
    isPathEnabled(link.href, modules),
  );

  return (
    <nav
      className={cn(
        "flex flex-wrap items-center gap-1 text-sm",
        vertical && "flex-col flex-nowrap items-stretch",
        className,
      )}
    >
      {links.map((link) => {
        const active =
          pathname === link.href || pathname.startsWith(`${link.href}/`);
        const isForum = link.href === "/forum";

        return (
          <Link
            key={link.href}
            href={link.href}
            onClick={onNavigate}
            className={cn(
              "inline-flex items-center rounded-lg px-3 font-medium transition",
              vertical ? "w-full py-3" : "h-10 justify-center",
              isForum &&
                !active &&
                "text-primary/85 hover:bg-primary/8 hover:text-primary",
              isForum && active && "bg-primary/12 text-primary",
              !isForum &&
                (active
                  ? "bg-primary/10 text-primary"
                  : "text-muted-foreground hover:bg-secondary hover:text-foreground"),
            )}
          >
            {t(link.labelKey)}
          </Link>
        );
      })}
    </nav>
  );
}
