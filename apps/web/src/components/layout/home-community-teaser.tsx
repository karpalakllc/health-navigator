import Link from "next/link";
import { Card } from "@/components/ui/card";
import { t } from "@/i18n/t";

export function HomeCommunityTeaser() {
  return (
    <ul className="grid gap-4 sm:grid-cols-2">
      <li>
        <Link href="/forum" className="group block h-full">
          <Card className="card-hover relative h-full overflow-hidden p-5">
            <span
              className="pointer-events-none absolute -right-8 -top-8 h-24 w-24 rounded-full bg-primary/10 blur-2xl transition-all duration-500 group-hover:bg-primary/20"
              aria-hidden="true"
            />
            <h3 className="relative font-semibold text-foreground">
              {t("home.communityForumTitle")}
            </h3>
            <p className="relative mt-2 text-sm text-muted-foreground">
              {t("home.communityForumDesc")}
            </p>
            <p className="relative mt-4 text-sm font-medium text-primary">
              {t("home.communityForumCta")} →
            </p>
          </Card>
        </Link>
      </li>
      <li>
        <Link href="/guidance" className="group block h-full">
          <Card className="card-hover relative h-full overflow-hidden p-5">
            <span
              className="pointer-events-none absolute -bottom-10 -right-6 h-28 w-28 rounded-full bg-accent/15 blur-2xl transition-all duration-500 group-hover:bg-accent/25"
              aria-hidden="true"
            />
            <h3 className="relative font-semibold text-foreground">
              {t("home.communityGuidanceTitle")}
            </h3>
            <p className="relative mt-2 text-sm text-muted-foreground">
              {t("home.communityGuidanceDesc")}
            </p>
            <p className="relative mt-4 text-sm font-medium text-accent">
              {t("home.communityGuidanceCta")} →
            </p>
          </Card>
        </Link>
      </li>
    </ul>
  );
}
