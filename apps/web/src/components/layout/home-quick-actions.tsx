import Link from "next/link";
import { Card } from "@/components/ui/card";
import { cn } from "@/lib/cn";
import { t } from "@/i18n/t";

const items = [
  {
    href: "/doctors",
    titleKey: "home.doctorsTitle" as const,
    descKey: "home.doctorsDesc" as const,
    icon: (
      <path
        strokeLinecap="round"
        strokeLinejoin="round"
        d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z"
      />
    ),
    iconWrap: "bg-primary/10 text-primary",
  },
  {
    href: "/facilities",
    titleKey: "home.facilitiesTitle" as const,
    descKey: "home.facilitiesDesc" as const,
    icon: (
      <path
        strokeLinecap="round"
        strokeLinejoin="round"
        d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3.75h.008v.008h-.008v-.008Zm0 3h.008v.008h-.008v-.008Zm0 3h.008v.008h-.008v-.008Z"
      />
    ),
    iconWrap: "bg-accent/15 text-accent",
  },
  {
    href: "/pharmacies",
    titleKey: "home.pharmaciesTitle" as const,
    descKey: "home.pharmaciesDesc" as const,
    icon: (
      <path
        strokeLinecap="round"
        strokeLinejoin="round"
        d="M9.75 3.104v5.714a2.25 2.25 0 0 1-.659 1.591L5 14.5M9.75 3.104c-.251.023-.501.05-.75.082m.75-.082a24.301 24.301 0 0 1 4.5 0m0 0v5.714c0 .597.237 1.17.659 1.591L19.8 15.3M14.25 3.104c.251.023.501.05.75.082M19.8 15.3l-1.57.393A9.065 9.065 0 0 1 12 15a9.065 9.065 0 0 0-6.23-.693L5 14.5m14.8.8 1.402 1.402c1.232 1.232.65 3.318-1.067 3.611A48.309 48.309 0 0 1 12 21c-2.773 0-5.491-.235-8.135-.687-1.718-.293-2.3-2.379-1.067-3.61L5 14.5"
      />
    ),
    iconWrap: "bg-cyan-500/10 text-cyan-700 dark:text-cyan-400",
  },
  {
    href: "/products",
    titleKey: "home.productsTitle" as const,
    descKey: "home.productsDesc" as const,
    icon: (
      <path
        strokeLinecap="round"
        strokeLinejoin="round"
        d="m21 7.5-9-5.25L3 7.5m18 0-9 5.25m9-5.25v9l-9 5.25M3 7.5l9 5.25M3 7.5v9l9 5.25m0-9v9"
      />
    ),
    iconWrap: "bg-violet-500/10 text-violet-700 dark:text-violet-400",
  },
  {
    href: "/guidance",
    titleKey: "nav.guidance" as const,
    descKey: "home.guidanceCardDesc" as const,
    icon: (
      <path
        strokeLinecap="round"
        strokeLinejoin="round"
        d="M12 18v-5.25m0 0a6.01 6.01 0 0 0 1.5-.189m-1.5.189a6.01 6.01 0 0 1-1.5-.189m3.75 7.478a12.06 12.06 0 0 1-4.5 0m3.75 2.383a14.406 14.406 0 0 1-3 0M14.25 18v-.192c0-.983.658-1.823 1.508-2.316a7.5 7.5 0 1 0-7.517 0c.85.493 1.509 1.333 1.509 2.316V18"
      />
    ),
    iconWrap: "bg-amber-500/15 text-amber-800 dark:text-amber-400",
  },
  {
    href: "/forum",
    titleKey: "nav.forum" as const,
    descKey: "home.forumDesc" as const,
    icon: (
      <path
        strokeLinecap="round"
        strokeLinejoin="round"
        d="M20.25 8.511c.884.284 1.5 1.128 1.5 2.097v4.286c0 1.136-.847 2.1-1.98 2.193-.34.027-.68.052-1.02.072v3.091l-3-3c-1.354 0-2.694-.055-4.02-.163a2.115 2.115 0 0 1-.825-.242m9.345-8.334a2.126 2.126 0 0 0-.476-.095 48.64 48.64 0 0 0-8.048 0c-1.131.094-1.976 1.057-1.976 2.192v4.286c0 .837.46 1.58 1.155 1.951m9.345-8.334V6.637c0-1.621-1.152-3.026-2.76-3.235A48.455 48.455 0 0 0 11.25 3c-2.115 0-4.198.137-6.24.402-1.608.209-2.76 1.614-2.76 3.235v6.226c0 1.621 1.152 3.026 2.76 3.235.577.075 1.157.14 1.74.194V21l4.155-4.155"
      />
    ),
    iconWrap: "bg-slate-500/10 text-slate-700 dark:text-slate-300",
  },
] as const;

type HomeQuickActionsProps = {
  showPharmacies?: boolean;
  showProducts?: boolean;
  showGuidance?: boolean;
  showForum?: boolean;
};

export function HomeQuickActions({
  showPharmacies = true,
  showProducts = true,
  showGuidance = true,
  showForum = true,
}: HomeQuickActionsProps) {
  const visible = items.filter((item) => {
    if (item.href === "/pharmacies") {
      return showPharmacies;
    }

    if (item.href === "/products") {
      return showProducts;
    }

    if (item.href === "/guidance") {
      return showGuidance;
    }

    if (item.href === "/forum") {
      return showForum;
    }

    return true;
  });

  return (
    <ul className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
      {visible.map((item) => (
        <li key={item.href}>
          <Link href={item.href} className="block min-h-[44px]">
            <Card className="card-hover flex h-full gap-4 border-border p-5">
              <span
                className={cn(
                  "flex h-12 w-12 shrink-0 items-center justify-center rounded-xl",
                  item.iconWrap,
                )}
              >
                <svg className="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5} aria-hidden>
                  {item.icon}
                </svg>
              </span>
              <div className="min-w-0 space-y-1">
                <h3 className="font-semibold text-foreground">{t(item.titleKey)}</h3>
                <p className="text-sm text-muted-foreground">{t(item.descKey)}</p>
              </div>
            </Card>
          </Link>
        </li>
      ))}
    </ul>
  );
}
