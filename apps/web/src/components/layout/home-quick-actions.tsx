import Link from "next/link";
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
    iconWrap: "icon-soft-red",
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
    iconWrap: "icon-soft-teal",
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
    iconWrap: "icon-soft-red",
  },
] as const;

export function HomeQuickActions({ showForum = true }: { showForum?: boolean }) {
  const visible = showForum ? items : items.filter((item) => item.href !== "/forum");

  return (
    <ul className="grid gap-[18px] md:grid-cols-3 md:items-stretch">
      {visible.map((item) => (
        <li key={item.href} className="flex min-h-0">
          <Link href={item.href} className="flex min-h-[44px] flex-1">
            <article className="surface-glass card-lift flex h-full w-full flex-col rounded-[26px] p-6">
              <span
                className={cn(
                  "mb-4 flex h-[54px] w-[54px] shrink-0 items-center justify-center rounded-[18px]",
                  item.iconWrap,
                )}
              >
                <svg className="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5} aria-hidden>
                  {item.icon}
                </svg>
              </span>
              <h3 className="text-[1.08rem] font-extrabold tracking-tight text-foreground">{t(item.titleKey)}</h3>
              <p className="mt-2 flex-1 text-sm leading-relaxed text-muted-foreground">{t(item.descKey)}</p>
            </article>
          </Link>
        </li>
      ))}
    </ul>
  );
}
