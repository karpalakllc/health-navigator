import { Notice } from "@/components/ui/notice";
import { t } from "@/i18n/t";

export function PriceDisclaimer({ className }: { className?: string }) {
  return (
    <Notice tone="info" className={className}>
      {t("catalog.priceDisclaimer")}
    </Notice>
  );
}
