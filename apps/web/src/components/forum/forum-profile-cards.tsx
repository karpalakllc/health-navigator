import Link from "next/link";
import { Icon, type IconName } from "@/components/ui/icons";
import type { ForumProfileCard } from "@/lib/api/forum";
import { safePath } from "@/lib/notification-text";
import { t } from "@/i18n/t";

const ICONS: Record<ForumProfileCard["kind"], IconName> = {
  doctor: "stethoscope",
  facility: "building",
  pharmacy: "pill",
};

/**
 * Profiles a post links to (the API resolves pasted Zdravje360 links to
 * published profiles): small linked cards under the body.
 */
export function ForumProfileCards({ cards }: { cards?: ForumProfileCard[] }) {
  const items = (cards ?? []).filter((card) => safePath(card.path));

  if (items.length === 0) {
    return null;
  }

  return (
    <ul
      aria-label={t("forum.profileCardsLabel")}
      className="m-0 flex list-none flex-col gap-2 p-0 sm:flex-row sm:flex-wrap"
    >
      {items.map((card) => {
        const meta = [
          t(`forum.profileKind.${card.kind}`),
          card.subtitle,
          card.city,
        ].filter(Boolean);

        return (
          <li key={`${card.kind}-${card.slug}`} className="sm:max-w-[22rem]">
            <Link
              href={card.path}
              className="flex min-h-12 items-center gap-3 rounded-card border border-line bg-cream px-3 py-2 transition-colors hover:bg-sand"
            >
              <span className="inline-flex size-10 shrink-0 items-center justify-center rounded-full bg-apricot text-ink">
                <Icon name={ICONS[card.kind]} size={20} />
              </span>
              <span className="flex min-w-0 flex-col">
                <span className="truncate font-ui font-semibold text-ink">
                  {card.name}
                </span>
                <span className="truncate type-meta text-ink-2">
                  {meta.join(" · ")}
                </span>
              </span>
              <Icon
                name="chevron-right"
                size={20}
                className="ml-auto shrink-0 text-ink-2"
              />
            </Link>
          </li>
        );
      })}
    </ul>
  );
}
