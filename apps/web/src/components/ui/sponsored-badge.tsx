import { DisclosureBadge } from "@/components/ui/disclosure-badge";
import { t } from "@/i18n/t";

type SponsoredBadgeProps = {
  className?: string;
  /** Kept for the existing callers; both sizes are the 32px tag. */
  size?: "sm" | "md";
};

/**
 * Partner / paid-placement label — distinct from organic directory rows.
 * Neutral outline (ink-2 on white, 7.6:1): sponsored must never look urgent,
 * so no coral. The „i“ toggletip says what it means on hover, tap and keyboard.
 */
export function SponsoredBadge({ className }: SponsoredBadgeProps) {
  return (
    <DisclosureBadge
      tone="outline"
      label={t("doctors.sponsored")}
      buttonLabel={t("disclosure.sponsoredWhy")}
      explanation={t("disclosure.sponsoredInfo")}
      className={className}
    />
  );
}
