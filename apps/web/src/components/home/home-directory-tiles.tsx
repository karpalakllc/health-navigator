import Link from "next/link";
import { Icon, type IconName } from "@/components/ui/icons";
import { cn } from "@/lib/cn";
import { t } from "@/i18n/t";

export type HomeTile = {
  href: string;
  label: string;
  icon: IconName;
  /** Count line („1.240 профили“) or a short prompt; omitted when unknown. */
  sub?: string;
  /**
   * Apricot feature tile instead of white. Set per tile by the page (today
   * Лекари and Форум), never inferred from a tile's position.
   */
  feature?: boolean;
};

/**
 * „Што ви треба?“: one tile per enabled directory. 2 columns on mobile, one
 * row of up to 6 on desktop. The whole tile is the link; its name is the
 * label plus the count line.
 */
export function HomeDirectoryTiles({
  tiles,
  title = t("home.tilesTitle"),
  headingId = "home-tiles-title",
  className,
}: {
  tiles: HomeTile[];
  title?: string;
  headingId?: string;
  className?: string;
}) {
  return (
    <section aria-labelledby={headingId} className={className}>
      <h2 id={headingId} className="type-h2 text-ink">
        {title}
      </h2>
      <ul
        className={cn(
          "mt-3 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:mt-6 lg:gap-6",
          tiles.length >= 6 ? "lg:grid-cols-6" : "lg:grid-cols-4",
        )}
      >
        {tiles.map((tile) => (
          <li key={tile.href} className="flex">
            <Link
              href={tile.href}
              className={cn(
                "card hover-lift flex min-h-32 w-full flex-col justify-between gap-4 p-4 text-ink lg:min-h-[140px] lg:p-5",
                tile.feature && "bg-apricot",
              )}
            >
              <span className="flex items-start justify-between">
                <span
                  className={cn(
                    "flex size-11 items-center justify-center rounded-full",
                    tile.feature ? "bg-white" : "bg-sand",
                  )}
                >
                  <Icon name={tile.icon} />
                </span>
                <Icon
                  name="arrow-right"
                  size={20}
                  className="icon-nudge hidden lg:block"
                />
              </span>
              <span className="block">
                <span className="block font-ui text-[1.0625rem] font-semibold leading-[1.375rem] lg:text-lg lg:leading-6">
                  {tile.label}
                </span>
                {/* A real space keeps „Лекари 10 профили“ apart in the
                    accessible name. */}{" "}
                {tile.sub ? (
                  <span className="mt-0.5 block text-[0.9375rem] leading-5 text-ink-2 lg:text-base lg:leading-[1.375rem]">
                    {tile.sub}
                  </span>
                ) : null}
              </span>
            </Link>
          </li>
        ))}
      </ul>
    </section>
  );
}
