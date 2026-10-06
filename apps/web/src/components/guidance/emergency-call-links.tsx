import { t } from "@/i18n/t";

/**
 * Tap-to-call links for the emergency outcome. Real tel: anchors, so a phone
 * dials straight away instead of the visitor copying a number from the text.
 */
export function EmergencyCallLinks() {
  return (
    <div className="flex flex-col gap-3 sm:flex-row sm:flex-wrap">
      <a
        href="tel:194"
        className="inline-flex min-h-[48px] items-center justify-center rounded-xl bg-destructive px-5 py-3 text-base font-bold text-white transition hover:brightness-110 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
      >
        {t("guidance.call194")}
      </a>
      <a
        href="tel:112"
        className="inline-flex min-h-[48px] items-center justify-center rounded-xl border border-destructive/40 bg-card px-5 py-3 text-sm font-semibold text-destructive transition hover:bg-destructive/5 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
      >
        {t("guidance.call112")}
      </a>
    </div>
  );
}
