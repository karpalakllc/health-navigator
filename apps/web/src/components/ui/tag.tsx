import type { ReactNode } from "react";
import { Icon, type IconName } from "@/components/ui/icons";
import { cn } from "@/lib/cn";
import { t } from "@/i18n/t";

/*
 * Non-interactive labels: 32px pills, 15/500. Contrast (all ≥4.5:1):
 * sand/ink 13.3, care-tint/care 5.4, chip-tint/ink 13.3, outline ink-2 on
 * white 7.6, ink/white 15.6.
 */
const tones = {
  /** „Автор“, „16 години искуство“, categories, services. */
  sand: "bg-sand text-ink",
  /** „Прима нови пациенти“, „Отворено“, „Проверена посета“, verified. */
  care: "bg-care-tint text-care",
  /** „Без одговор“ and other soft-brand labels. */
  tint: "bg-chip-tint text-ink",
  /** „Истакнат“ (sponsored) — deliberately neutral, never coral. */
  outline:
    "bg-white text-ink-2 shadow-[inset_0_0_0_1px_var(--color-line-strong)]",
  /** „Денес“, counters. */
  ink: "bg-ink text-white",
  /** On apricot / sand surfaces. */
  white: "bg-white text-ink",
} as const;

export type TagTone = keyof typeof tones;

export function Tag({
  tone = "sand",
  icon,
  className,
  children,
  ...rest
}: Omit<React.HTMLAttributes<HTMLSpanElement>, "children"> & {
  tone?: TagTone;
  icon?: IconName;
  children: ReactNode;
}) {
  return (
    <span className={cn("tag", tones[tone], className)} {...rest}>
      {icon ? <Icon name={icon} size={16} /> : null}
      <span>{children}</span>
    </span>
  );
}

/** Neutral „Истакнат“ tag for featured placements. */
export function FeaturedTag({ className }: { className?: string }) {
  return (
    <Tag tone="outline" className={className}>
      {t("ui.featured")}
    </Tag>
  );
}

/** Care-green „Верификуван“ (or a custom label, e.g. „Здравствен работник“). */
export function VerifiedTag({
  children,
  className,
}: {
  children?: ReactNode;
  className?: string;
}) {
  return (
    <Tag tone="care" icon="shield-check" className={className}>
      {children ?? t("ui.verified")}
    </Tag>
  );
}
