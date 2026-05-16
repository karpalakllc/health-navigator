import Link from "next/link";
import { Card } from "@/components/ui/card";
import type { ForumCategory } from "@/lib/api/forum";
import { authorInitials } from "@/lib/format";
import { t, tFormat } from "@/i18n/t";

export function ForumCategoryCard({ category }: { category: ForumCategory }) {
  const initials = authorInitials(category.name);

  return (
    <Link href={`/forum/${category.slug}`} className="group block h-full">
      <Card className="card-hover flex h-full flex-col gap-4 p-5 transition-shadow">
        <div className="flex items-start gap-4">
          <span
            className="flex size-12 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-primary/15 to-accent/20 text-sm font-bold text-primary ring-1 ring-primary/10"
            aria-hidden
          >
            {initials}
          </span>
          <div className="min-w-0 flex-1 space-y-1">
            <h2 className="font-semibold text-foreground group-hover:text-primary">{category.name}</h2>
            {category.description ? (
              <p className="line-clamp-2 text-sm text-muted-foreground">{category.description}</p>
            ) : null}
          </div>
        </div>
        <p className="mt-auto text-xs font-medium text-muted-foreground">
          {category.topics_count != null
            ? tFormat("forum.topicsCount", { count: String(category.topics_count) })
            : t("forum.topics")}
        </p>
      </Card>
    </Link>
  );
}
