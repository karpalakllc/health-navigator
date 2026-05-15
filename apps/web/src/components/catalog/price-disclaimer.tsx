import { t } from "@/i18n/t";

export function PriceDisclaimer() {
  return (
    <p className="rounded-lg border border-zinc-200 bg-white px-4 py-3 text-sm text-zinc-600">
      {t("catalog.priceDisclaimer")}
    </p>
  );
}
