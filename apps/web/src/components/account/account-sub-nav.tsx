import Link from "next/link";
import { Icon, type IconName } from "@/components/ui/icons";
import { cn } from "@/lib/cn";
import { t } from "@/i18n/t";

export type AccountSection =
  "overview" | "reviews" | "forum" | "devices" | "data" | "doctor";

const sections: {
  id: AccountSection;
  href: string;
  icon: IconName;
  labelKey:
    | "account.navOverview"
    | "nav.myReviews"
    | "nav.myForum"
    | "account.navDevices"
    | "account.navData"
    | "doctorDashboard.navLabel";
}[] = [
  {
    id: "overview",
    href: "/account",
    icon: "user",
    labelKey: "account.navOverview",
  },
  // Only for an account staff linked to a doctor profile (showDoctor).
  {
    id: "doctor",
    href: "/account/doctor",
    icon: "stethoscope",
    labelKey: "doctorDashboard.navLabel",
  },
  {
    id: "reviews",
    href: "/account/reviews",
    icon: "star",
    labelKey: "nav.myReviews",
  },
  {
    id: "forum",
    href: "/account/forum",
    icon: "message-circle",
    labelKey: "nav.myForum",
  },
  {
    id: "devices",
    href: "/account/devices",
    icon: "lock",
    labelKey: "account.navDevices",
  },
  {
    id: "data",
    href: "/account/data",
    icon: "file-text",
    labelKey: "account.navData",
  },
];

type AccountSubNavProps = {
  current: AccountSection;
  /** The account manages a doctor profile: show „Мој профил“. */
  showDoctor?: boolean;
  className?: string;
};

/**
 * Account sections. A row of chip-style pills on a phone (scrolls if it must),
 * a vertical list on desktop; the current one is ink-filled / sand and
 * carries aria-current.
 */
export function AccountSubNav({
  current,
  showDoctor = false,
  className,
}: AccountSubNavProps) {
  return (
    <nav aria-label={t("account.subNavAria")} className={className}>
      <ul className="scroll-row -mx-5 -my-2.5 flex gap-2 px-5 py-2.5 lg:mx-0 lg:my-0 lg:flex-col lg:gap-1 lg:overflow-visible lg:px-0 lg:py-0">
        {sections
          .filter((item) => item.id !== "doctor" || showDoctor)
          .map((item) => {
            const active = item.id === current;

            return (
              <li key={item.id} className="shrink-0">
                <Link
                  href={item.href}
                  aria-current={active ? "page" : undefined}
                  className={cn(
                    "flex min-h-12 items-center gap-2 rounded-pill px-4 type-chip",
                    "lg:min-h-14 lg:gap-3 lg:px-5 lg:type-body",
                    active
                      ? "bg-ink font-semibold text-white lg:bg-sand lg:text-ink"
                      : "bg-white text-ink shadow-[inset_0_0_0_1px_var(--color-line-strong)] hover:bg-sand lg:bg-transparent lg:shadow-none",
                  )}
                >
                  <Icon name={item.icon} size={20} />
                  <span>{t(item.labelKey)}</span>
                </Link>
              </li>
            );
          })}
      </ul>
    </nav>
  );
}
