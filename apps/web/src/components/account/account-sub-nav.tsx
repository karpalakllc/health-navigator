import Link from "next/link";
import { cn } from "@/lib/cn";
import { t } from "@/i18n/t";

export type AccountSection = "overview" | "reviews" | "forum";

const sections: {
  id: AccountSection;
  href: string;
  labelKey: "account.navOverview" | "nav.myReviews" | "nav.myForum";
}[] = [
  { id: "overview", href: "/account", labelKey: "account.navOverview" },
  { id: "reviews", href: "/account/reviews", labelKey: "nav.myReviews" },
  { id: "forum", href: "/account/forum", labelKey: "nav.myForum" },
];

type AccountSubNavProps = {
  current: AccountSection;
  className?: string;
};

export function AccountSubNav({ current, className }: AccountSubNavProps) {
  return (
    <nav
      aria-label={t("account.subNavAria")}
      className={cn(
        "content-card flex flex-row gap-1 overflow-x-auto rounded-[1.25rem] p-1 lg:flex-col lg:overflow-visible",
        className,
      )}
    >
      {sections.map((item) => {
        const active = item.id === current;

        return (
          <Link
            key={item.id}
            href={item.href}
            className={cn(
              "flex min-h-[44px] shrink-0 items-center rounded-lg px-3 py-2.5 text-sm font-medium transition lg:min-h-0",
              active
                ? "bg-primary/10 text-primary"
                : "text-muted-foreground hover:bg-muted hover:text-foreground",
            )}
          >
            {t(item.labelKey)}
          </Link>
        );
      })}
    </nav>
  );
}
