import Link from "next/link";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import { Icon } from "@/components/ui/icons";
import type { ForumCategory } from "@/lib/api/forum";
import { cn } from "@/lib/cn";
import { t, tCount } from "@/i18n/t";

function topicsLabel(category: ForumCategory) {
  return category.topics_count != null
    ? tCount("forum.topicsCount", category.topics_count)
    : t("forum.topics");
}

/** Categories as rows in one white card: icon disc, name, count, chevron. */
export function ForumCategoryList({
  categories,
  headingLevel = 3,
  className,
}: {
  categories: ForumCategory[];
  headingLevel?: 2 | 3;
  className?: string;
}) {
  const Heading = headingLevel === 2 ? "h2" : "h3";

  return (
    <Card padding="none" className={cn("overflow-hidden", className)}>
      <ul>
        {categories.map((category) => (
          <li
            key={category.slug}
            className="relative flex min-h-[4.5rem] items-center gap-3 border-t border-line px-4 py-3.5 first:border-t-0 hover:bg-cream sm:px-5"
          >
            <span
              aria-hidden="true"
              className="inline-flex size-11 shrink-0 items-center justify-center rounded-full bg-apricot text-ink"
            >
              <Icon name="message-circle" size={22} />
            </span>
            <div className="flex min-w-0 flex-1 flex-col">
              <Heading className="font-ui text-[1.0625rem] leading-6 font-semibold text-ink">
                <Link
                  href={`/forum/${category.slug}`}
                  className="decoration-coral decoration-2 underline-offset-4 after:absolute after:inset-0 after:content-[''] hover:underline"
                >
                  {category.name}
                </Link>
              </Heading>
              {category.description ? (
                <p className="line-clamp-2 type-meta text-ink-2">
                  {category.description}
                </p>
              ) : null}
              <p className="type-meta font-medium text-ink-2">
                {topicsLabel(category)}
              </p>
            </div>
            <Icon name="chevron-right" size={20} className="text-ink" />
          </li>
        ))}
      </ul>
    </Card>
  );
}

/** The current category in the thread aside: disc, name, count, all topics. */
export function ForumCategorySummary({
  category,
  slug,
  className,
}: {
  category: Pick<ForumCategory, "name" | "topics_count">;
  slug: string;
  className?: string;
}) {
  return (
    <Card padding="md" className={cn("flex flex-col gap-4", className)}>
      <div className="flex items-center gap-3">
        <span
          aria-hidden="true"
          className="inline-flex size-11 shrink-0 items-center justify-center rounded-full bg-apricot text-ink"
        >
          <Icon name="message-circle" size={22} />
        </span>
        <div className="flex min-w-0 flex-col">
          <p className="type-h3 text-ink">{category.name}</p>
          {category.topics_count != null ? (
            <p className="type-meta text-ink-2">
              {tCount("forum.topicsCount", category.topics_count)}
            </p>
          ) : null}
        </div>
      </div>
      <Button href={`/forum/${slug}`} variant="soft" fullWidth>
        {t("forum.categoryAllTopics")}
      </Button>
    </Card>
  );
}
