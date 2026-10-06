import type { ReactNode, Ref } from "react";
import { EmergencyCallLinks } from "@/components/guidance/emergency-call-links";
import { Icon } from "@/components/ui/icons";
import { t } from "@/i18n/t";

/*
 * The emergency screen (NHS emergency care card): an ink-framed card with an
 * ink „ИТНО“ band, one heading that says what to do, and the 194/112 call
 * links as the only actions. Emergency red stays on the call links.
 */
export function EmergencyCard({
  title,
  body,
  headingRef,
  children,
}: {
  title: string;
  body: string;
  headingRef: Ref<HTMLHeadingElement>;
  children?: ReactNode;
}) {
  return (
    <section
      aria-labelledby="guidance-emergency-title"
      className="overflow-hidden rounded-sheet border-[3px] border-ink bg-white"
    >
      <p className="flex items-center gap-2 bg-ink px-5 py-3 type-label uppercase tracking-[0.08em] text-white lg:px-8">
        <Icon name="alert-triangle" size={20} />
        {t("guidance.emergencyBand")}
      </p>
      <div className="flex flex-col gap-5 p-4 sm:p-5 lg:gap-6 lg:p-8">
        <h2
          id="guidance-emergency-title"
          ref={headingRef}
          tabIndex={-1}
          className="type-h1 text-ink"
        >
          {title}
        </h2>
        <p className="type-reading measure text-ink">{body}</p>
        <EmergencyCallLinks />
        {children}
      </div>
    </section>
  );
}
