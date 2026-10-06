import { ChipLink } from "@/components/ui/chip";
import type { ForumTag } from "@/lib/api/forum";
import { t } from "@/i18n/t";

/**
 * The topic's keywords as chip links to their tag pages. Rendered as a
 * labelled list so screen readers hear „Клучни зборови, list, 2 items“.
 */
export function ForumTagRow({ tags }: { tags: ForumTag[] }) {
  if (tags.length === 0) {
    return null;
  }

  return (
    <nav aria-label={t("seo.tagsLabel")}>
      <ul className="flex flex-wrap gap-2">
        {tags.map((tag) => (
          <li key={tag.slug}>
            <ChipLink href={`/forum/tags/${encodeURIComponent(tag.slug)}`}>
              {tag.name}
            </ChipLink>
          </li>
        ))}
      </ul>
    </nav>
  );
}
