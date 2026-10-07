import { Icon } from "@/components/ui/icons";
import { FIRST_AID_COPY } from "@/content/first-aid/copy";

/**
 * Tap-to-call 194 (and 112) for the first-aid pages: real tel: anchors. Same
 * look as the guidance emergency outcome (emergency red is reserved for these
 * calls), but its own component — the guidance UI belongs to another package.
 * The number is in the text itself, so nothing depends on colour, and a
 * printed page still shows „194“.
 */
export function FirstAidCallLinks() {
  return (
    <div className="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center">
      <a
        href="tel:194"
        className="inline-flex min-h-16 items-center justify-center gap-3 rounded-full border-2 border-ink bg-emergency px-6 py-2 text-center text-xl font-bold leading-7 text-white no-underline hover:bg-emergency-hover print:min-h-0 print:border-0 print:bg-transparent print:p-0 print:text-ink"
      >
        <Icon name="phone" size={24} />
        <span>{FIRST_AID_COPY.call194}</span>
      </a>
      <a
        href="tel:112"
        className="inline-flex min-h-14 items-center justify-center gap-2 rounded-full border-2 border-ink bg-white px-5 py-2 text-center text-lg font-semibold leading-6 text-emergency no-underline hover:bg-sand print:min-h-0 print:border-0 print:p-0 print:text-ink"
      >
        <Icon name="phone" size={20} />
        <span>{FIRST_AID_COPY.call112}</span>
      </a>
    </div>
  );
}
