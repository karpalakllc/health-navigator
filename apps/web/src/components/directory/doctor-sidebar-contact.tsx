import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import type { DoctorDetail } from "@/lib/api/types";
import { facilityPublicPath } from "@/lib/facility-labels";
import { t } from "@/i18n/t";

export function DoctorSidebarContact({ doctor }: { doctor: DoctorDetail }) {
  const primaryFacility = doctor.facilities.find((f) => f.is_primary) ?? doctor.facilities[0];
  const telHref = doctor.phone ? `tel:${doctor.phone.replace(/\s+/g, "")}` : null;

  return (
    <Card className="glass space-y-5 border-border/70 p-6 shadow-[0_22px_56px_-30px_rgb(15_23_42/0.42)]">
      <h2 className="text-lg font-semibold tracking-tight">{t("doctors.quickActionsTitle")}</h2>

      <div className="flex flex-col gap-3">
        {telHref ? (
          <a
            href={telHref}
            className="inline-flex h-12 items-center justify-center gap-2 rounded-xl bg-primary px-4 text-sm font-semibold text-primary-foreground shadow-[0_10px_28px_-14px_color-mix(in_srgb,var(--color-primary)_60%,transparent)] transition hover:bg-primary/92 active:scale-[0.98] motion-reduce:active:scale-100"
          >
            <PhoneIcon className="h-5 w-5 shrink-0" aria-hidden />
            {t("doctors.sidebarCall")}
          </a>
        ) : (
          <p className="rounded-xl bg-secondary/60 px-3 py-2 text-xs text-muted-foreground">
            {t("doctors.sidebarNoPhone")}
          </p>
        )}

        {doctor.email ? (
          <a
            href={`mailto:${doctor.email}`}
            className="inline-flex h-11 items-center justify-center gap-2 rounded-xl border border-border bg-background/80 text-sm font-semibold transition hover:bg-secondary"
          >
            <MailIcon className="h-5 w-5 shrink-0 text-muted-foreground" aria-hidden />
            {t("doctors.sidebarEmail")}
          </a>
        ) : null}

        {primaryFacility ? (
          <Button
            href={facilityPublicPath(primaryFacility.type, primaryFacility.slug)}
            variant="outline"
            className="h-11 justify-center gap-2 font-semibold"
          >
            <PinIcon className="h-5 w-5 shrink-0" aria-hidden />
            {t("doctors.sidebarPrimaryFacility")}
          </Button>
        ) : (
          <Button href="#doctor-locations" variant="outline" className="h-11 justify-center gap-2 font-semibold">
            <PinIcon className="h-5 w-5 shrink-0" aria-hidden />
            {t("doctors.sidebarLocations")}
          </Button>
        )}
      </div>

      {doctor.consultation_fee_note ? (
        <p className="text-sm text-muted-foreground">
          <span className="font-medium text-foreground">{t("doctors.consultationFee")}: </span>
          {doctor.consultation_fee_note}
        </p>
      ) : null}

      <div className="rounded-xl border border-primary/15 bg-primary/[0.06] p-4 text-xs leading-relaxed text-muted-foreground">
        <div className="mb-2 flex items-center gap-2 font-semibold text-foreground">
          <ShieldIcon className="h-4 w-4 shrink-0 text-primary" aria-hidden />
          {t("doctors.sidebarDisclaimerTitle")}
        </div>
        {t("footer.informational")}
      </div>
    </Card>
  );
}

function PhoneIcon({ className }: { className?: string }) {
  return (
    <svg className={className} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
      <path
        d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07 19.5 19.5 0 01-6-6 19.79 19.79 0 01-3.07-8.67A2 2 0 014.11 2h3a2 2 0 012 1.72 12.84 12.84 0 00.7 2.81 2 2 0 01-.45 2.11L8.09 10.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45 12.84 12.84 0 002.81.7A2 2 0 0122 16.92z"
        strokeLinecap="round"
        strokeLinejoin="round"
      />
    </svg>
  );
}

function MailIcon({ className }: { className?: string }) {
  return (
    <svg className={className} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
      <path d="M4 4h16a2 2 0 012 2v12a2 2 0 01-2 2H4a2 2 0 01-2-2V6a2 2 0 012-2z" strokeLinecap="round" strokeLinejoin="round" />
      <path d="m22 6-10 7L2 6" strokeLinecap="round" strokeLinejoin="round" />
    </svg>
  );
}

function PinIcon({ className }: { className?: string }) {
  return (
    <svg className={className} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
      <path d="M12 21s7-4.35 7-11a7 7 0 10-14 0c0 6.65 7 11 7 11z" strokeLinecap="round" strokeLinejoin="round" />
      <circle cx="12" cy="10" r="2.5" />
    </svg>
  );
}

function ShieldIcon({ className }: { className?: string }) {
  return (
    <svg className={className} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
      <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" strokeLinecap="round" strokeLinejoin="round" />
    </svg>
  );
}
