import Link from "next/link";
import { Card } from "@/components/ui/card";
import type { ForumCategory } from "@/lib/api/forum";
import { t, tFormat } from "@/i18n/t";

export function ForumCategoryCard({ category }: { category: ForumCategory }) {
  return (
    <Link href={`/forum/${category.slug}`} className="block h-full">
      <Card className="card-hover flex h-full flex-col gap-2 p-5">
        <h2 className="font-semibold text-foreground">{category.name}</h2>
        {category.description ? (
          <p className="text-sm text-muted-foreground">{category.description}</p>
        ) : null}
        {category.topics_count != null ? (
          <p className="mt-auto text-xs text-muted-foreground">
            {tFormat("forum.topicsCount", { count: String(category.topics_count) })}
          </p>
        ) : (
          <p className="mt-auto text-xs text-muted-foreground">{t("forum.topics")}</p>
        )}
      </Card>
    </Link>
  );
}
