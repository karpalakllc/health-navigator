import { Tag } from "@/components/ui/tag";
import { t } from "@/i18n/t";

/**
 * „Лиценца: важечка“ on a doctor's profile when the Лекарска комора list
 * shows a licence valid today. Nothing otherwise: an expired licence and one
 * we could not match look the same, so neither is announced.
 */
export function LicenceStatusTag({
  valid,
}: {
  valid: boolean | null | undefined;
}) {
  if (valid !== true) {
    return null;
  }

  return <Tag icon="shield-check">{t("doctors.licenceValid")}</Tag>;
}
