import type { ReactNode } from "react";
import { DisclosureBadge } from "@/components/ui/disclosure-badge";
import { Icon, type IconName } from "@/components/ui/icons";
import { tagTones, type TagTone } from "@/components/ui/tag-tones";
import { cn } from "@/lib/cn";
import { t } from "@/i18n/t";

export type { TagTone };

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
    <span className={cn("tag", tagTones[tone], className)} {...rest}>
      {icon ? <Icon name={icon} size={16} /> : null}
      <span>{children}</span>
    </span>
  );
}

/**
 * Neutral „Истакнат“ tag for featured placements, with a toggletip that says
 * why (owner rule: a featured label always explains itself).
 */
export function FeaturedTag({ className }: { className?: string }) {
  return (
    <DisclosureBadge
      tone="outline"
      label={t("ui.featured")}
      buttonLabel={t("disclosure.featuredWhy")}
      explanation={t("disclosure.featuredInfo")}
      className={className}
    />
  );
}

/** Care-green staff tag: „Тим“, or the role passed in (e.g. „Модератор“). */
export function VerifiedTag({
  children,
  className,
}: {
  children?: ReactNode;
  className?: string;
}) {
  return (
    <Tag tone="care" icon="shield-check" className={className}>
      {children ?? t("ui.staffTag")}
    </Tag>
  );
}
