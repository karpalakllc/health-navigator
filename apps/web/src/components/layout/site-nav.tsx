"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import { Icon, type IconName } from "@/components/ui/icons";
import { cn } from "@/lib/cn";
import { isPathEnabled, type ModuleFlags } from "@/lib/site-modules";
import { t } from "@/i18n/t";

export const SITE_NAV_LINKS = [
  { href: "/doctors", labelKey: "nav.doctors" as const, icon: "stethoscope" },
  {
    href: "/facilities",
    labelKey: "nav.facilities" as const,
    icon: "building",
  },
  { href: "/pharmacies", labelKey: "nav.pharmacies" as const, icon: "pill" },
  { href: "/products", labelKey: "nav.products" as const, icon: "package" },
  { href: "/guidance", labelKey: "nav.guidance" as const, icon: "compass" },
  { href: "/forum", labelKey: "nav.forum" as const, icon: "message-circle" },
] as const satisfies ReadonlyArray<{
  href: string;
  labelKey: string;
  icon: IconName;
}>;

type SiteNavProps = {
  className?: string;
  onNavigate?: () => void;
  layout?: "horizontal" | "vertical";
  /** Switched-off modules are left out rather than linking to a dead end. */
  modules: ModuleFlags;
  /** Accessible name of the <nav> landmark. */
  label?: string;
};

export function isActivePath(pathname: string, href: string): boolean {
  return pathname === href || pathname.startsWith(`${href}/`);
}

/**
 * Section links. Horizontal (desktop header row 2): Geologica 17/500 ink, the
 * active link 600 with a 3px ink underline. Vertical (mobile drawer): 56px
 * rows with an icon in a sand circle.
 */
export function SiteNav({
  className,
  onNavigate,
  layout = "horizontal",
  modules,
  label = t("nav.sections"),
}: SiteNavProps) {
  const pathname = usePathname();
  const vertical = layout === "vertical";
  const links = SITE_NAV_LINKS.filter((link) =>
    isPathEnabled(link.href, modules),
  );

  return (
    <nav aria-label={label} className={className}>
      <ul
        className={cn(
          "m-0 flex list-none p-0",
          vertical ? "flex-col" : "items-stretch gap-7",
        )}
      >
        {links.map((link) => {
          // Only the current section is marked. Форум used to be coloured on
          // every page, so it read as the active item next to the real one.
          const active = isActivePath(pathname, link.href);

          return (
            <li key={link.href} className="flex">
              <Link
                href={link.href}
                onClick={onNavigate}
                aria-current={active ? "page" : undefined}
                className={cn(
                  "relative flex items-center text-ink no-underline",
                  vertical
                    ? "min-h-14 w-full gap-3 rounded-2xl px-2 text-[1.0625rem] transition-colors hover:bg-sand"
                    : "h-[52px] text-[1.0625rem] leading-[1.375rem] hover:text-black",
                  // Desktop: a hairline grows under the hovered section;
                  // the current one keeps its ink bar.
                  !vertical &&
                    !active &&
                    "after:absolute after:inset-x-0 after:bottom-0 after:h-[3px] after:scale-x-0 after:rounded-[2px] after:bg-line-strong after:transition-transform after:duration-[var(--duration-base)] hover:after:scale-x-100",
                  active ? "font-semibold" : "font-medium",
                  vertical && active && "bg-sand",
                )}
              >
                {vertical ? (
                  <span
                    className={cn(
                      "inline-flex size-10 items-center justify-center rounded-full",
                      active ? "bg-white" : "bg-sand",
                    )}
                  >
                    <Icon name={link.icon} size={22} />
                  </span>
                ) : null}
                <span className={vertical ? "flex-1" : undefined}>
                  {t(link.labelKey)}
                </span>
                {/* No trailing chevron in the drawer: these rows open a
                    page, they do not drill into a sub-list. */}
                {!vertical && active ? (
                  <span
                    aria-hidden="true"
                    data-active-indicator=""
                    className="motion-grow-x absolute inset-x-0 bottom-0 h-[3px] rounded-[2px] bg-ink"
                  />
                ) : null}
              </Link>
            </li>
          );
        })}
      </ul>
    </nav>
  );
}
