import { Icon } from "@/components/ui/icons";
import { t } from "@/i18n/t";

/**
 * The 194 / 112 strip at the top of „Каде веднаш“ — the only page outside
 * guidance that carries one (owner rule: this page is about urgent care).
 * Calm: an apricot band with one sentence, then the same tap-to-call pills as
 * the guidance emergency view, a size smaller.
 */
export function EmergencyStrip() {
  return (
    <section
      aria-labelledby="urgent-strip-title"
      className="flex flex-col gap-3 rounded-card bg-apricot p-4 sm:flex-row sm:items-center sm:justify-between lg:p-5"
    >
      <p className="type-body text-ink">
        <strong id="urgent-strip-title" className="font-semibold">
          {t("urgentCare.stripTitle")}
        </strong>{" "}
        {t("urgentCare.stripBody")}
      </p>
      <div className="flex flex-col gap-2 sm:shrink-0 sm:flex-row">
        <a
          href="tel:194"
          className="inline-flex min-h-12 items-center justify-center gap-2 rounded-full border-2 border-ink bg-emergency px-4 py-1.5 text-center text-base font-bold leading-6 text-white no-underline hover:bg-emergency-hover"
        >
          <Icon name="phone" size={20} />
          <span>{t("urgentCare.call194")}</span>
        </a>
        <a
          href="tel:112"
          className="inline-flex min-h-12 items-center justify-center gap-2 rounded-full border-2 border-ink bg-white px-4 py-1.5 text-center text-base font-semibold leading-6 text-emergency no-underline hover:bg-sand"
        >
          <Icon name="phone" size={20} />
          <span>{t("urgentCare.call112")}</span>
        </a>
      </div>
    </section>
  );
}
