import { VERIFICATION_MORE_HREF } from "@/components/directory/verification-badge";
import { DisclosureBadge } from "@/components/ui/disclosure-badge";
import { Tag } from "@/components/ui/tag";
import { formatListDate } from "@/lib/mk-date";
import { t, tFormat } from "@/i18n/t";

/**
 * „Лиценца: важечка“ on a doctor's profile when the Лекарска комора list
 * shows a licence valid today, with a toggletip that names the list and its
 * date („Според листата на Лекарска комора од 02.07.2026.“) rather than an
 * absolute claim. Nothing otherwise: an expired licence and one we could
 * not match look the same, so neither is announced.
 */
export function LicenceStatusTag({
  valid,
  listDate,
}: {
  valid: boolean | null | undefined;
  /** The list's date („2026-07-02“), from `licence_list_date`. */
  listDate?: string | null;
}) {
  if (valid !== true) {
    return null;
  }

  const date = formatListDate(listDate);

  if (!date) {
    return <Tag icon="shield-check">{t("doctors.licenceValid")}</Tag>;
  }

  return (
    <DisclosureBadge
      tone="sand"
      icon="shield-check"
      label={t("doctors.licenceValid")}
      buttonLabel={t("doctors.licenceSourceWhy")}
      explanation={tFormat("doctors.licenceSource", { date })}
      moreHref={VERIFICATION_MORE_HREF}
    />
  );
}
