import { Icon } from "@/components/ui/icons";
import { t } from "@/i18n/t";

/**
 * Tap-to-call links for the emergency outcome. Real tel: anchors, so a phone
 * dials straight away instead of the visitor copying a number from the text.
 *
 * D2a: 194 is the largest target on the screen (64px filled emergency pill
 * with the 2px ink frame); 112 is a white pill with the same frame and
 * emergency-red text (6.57:1). Emergency red is reserved for these and the
 * guidance urgent-help button.
 */
export function EmergencyCallLinks({
  extra = [],
}: {
  extra?: Array<{ number: string; label: string }>;
}) {
  return (
    <div className="flex flex-col gap-3 sm:flex-row sm:flex-wrap">
      <a
        href="tel:194"
        className="inline-flex min-h-16 items-center justify-center gap-3 rounded-full border-2 border-ink bg-emergency px-5 py-2 text-center text-lg font-bold leading-6 text-balance text-white no-underline hover:bg-emergency-hover"
      >
        <Icon name="phone" size={24} />
        <span>{t("guidance.call194")}</span>
      </a>
      <a
        href="tel:112"
        className="inline-flex min-h-14 items-center justify-center gap-3 rounded-full border-2 border-ink bg-white px-5 py-2 text-center text-base font-semibold leading-6 text-balance text-emergency no-underline hover:bg-sand"
      >
        <Icon name="phone" size={20} />
        <span>{t("guidance.call112")}</span>
      </a>
      {extra.map((line) => (
        <a
          key={line.number}
          href={`tel:${line.number.replace(/\s+/g, "")}`}
          className="inline-flex min-h-14 items-center justify-center gap-3 rounded-full border-2 border-ink bg-white px-5 py-2 text-center text-base font-semibold leading-6 text-balance text-ink no-underline hover:bg-sand"
        >
          <Icon name="phone" size={20} />
          <span>
            {line.label} — {line.number}
          </span>
        </a>
      ))}
    </div>
  );
}
