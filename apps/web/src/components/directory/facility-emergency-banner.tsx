import { EmergencyCallLinks } from "@/components/guidance/emergency-call-links";
import { Icon } from "@/components/ui/icons";
import { t } from "@/i18n/t";

/**
 * The emergency card on a facility that offers emergency care: a white card
 * with the 2px ink frame and the real 194 / 112 tel: buttons. Red appears
 * only in those buttons — never as a surface.
 */
export function FacilityEmergencyBanner() {
  return (
    <section
      aria-labelledby="facility-emergency-title"
      className="flex flex-col gap-4 rounded-card border-2 border-ink bg-white p-5 lg:p-8"
    >
      <div className="flex gap-3">
        <Icon name="alert-triangle" size={24} className="mt-0.5 text-ink" />
        <div className="flex flex-col gap-1">
          <h2 id="facility-emergency-title" className="type-h3 text-ink">
            {t("facilities.emergencyBannerTitle")}
          </h2>
          <p className="type-body text-ink">
            {t("facilities.emergencyBannerBody")}
          </p>
        </div>
      </div>
      <EmergencyCallLinks />
    </section>
  );
}
