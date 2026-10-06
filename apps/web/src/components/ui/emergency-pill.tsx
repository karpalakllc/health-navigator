import { Icon } from "@/components/ui/icons";
import { cn } from "@/lib/cn";
import { t } from "@/i18n/t";

/*
 * The only place emergency red #b42318 appears: a filled pill with white text
 * (6.57:1), a phone icon and a 2px ink frame, always a real tel: link. It is
 * the single persistent „Итно 194“ in the header and never sits on coral.
 *
 * The accessible name starts with the visible text („Итно 194, повикај Брза
 * помош“) so voice-control users can say what they see (WCAG 2.5.3).
 */
const SIZE = {
  /** Header pill, 44px. */
  md: "min-h-11 px-4 pl-3.5 text-base gap-2",
  /** Large call buttons (guidance outcome), 64px. */
  lg: "min-h-16 px-7 text-lg gap-3",
} as const;

export function EmergencyPill({
  size = "md",
  className,
}: {
  size?: keyof typeof SIZE;
  className?: string;
}) {
  return (
    <a
      href="tel:194"
      className={cn(
        "inline-flex shrink-0 items-center justify-center whitespace-nowrap rounded-full border-2 border-ink bg-emergency font-semibold leading-5 text-white no-underline hover:bg-[#9a1d13]",
        SIZE[size],
        className,
      )}
    >
      <Icon name="phone" size={size === "lg" ? 24 : 20} />
      <span>
        {t("ui.emergencyPill")}
        <span className="sr-only">, {t("ui.emergencyPillSr")}</span>
      </span>
    </a>
  );
}
