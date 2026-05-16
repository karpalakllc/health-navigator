import { Card } from "@/components/ui/card";
import { FacilityMapEmbed } from "@/components/directory/facility-map-embed";
import type { FacilityDetail } from "@/lib/api/types";
import { addressMapUrl } from "@/lib/maps";
import { t } from "@/i18n/t";

export function FacilitySidebarContact({ facility }: { facility: FacilityDetail }) {
  const telHref = facility.phone ? `tel:${facility.phone.replace(/\s+/g, "")}` : null;
  const mapFallback = addressMapUrl({
    name: facility.name,
    address: facility.address,
    city: facility.city,
  });

  return (
    <aside className="space-y-4 lg:sticky lg:top-24 lg:self-start">
      <Card className="glass space-y-5 border-border/70 p-6 shadow-[0_22px_56px_-30px_rgb(15_23_42/0.42)]">
        <h2 className="text-lg font-semibold tracking-tight">{t("facilities.sidebarContactTitle")}</h2>

        <div className="flex flex-col gap-3">
          {telHref ? (
            <a
              href={telHref}
              className="inline-flex h-12 items-center justify-center gap-2 rounded-xl bg-primary px-4 text-sm font-semibold text-primary-foreground shadow-[0_10px_28px_-14px_color-mix(in_srgb,var(--color-primary)_60%,transparent)] transition hover:bg-primary/92 active:scale-[0.98] motion-reduce:active:scale-100"
            >
              <PhoneIcon className="h-5 w-5 shrink-0" aria-hidden />
              {t("facilities.sidebarCall")}
            </a>
          ) : (
            <p className="rounded-xl bg-secondary/60 px-3 py-2 text-xs text-muted-foreground">
              {t("facilities.sidebarNoPhone")}
            </p>
          )}

          {facility.email ? (
            <a
              href={`mailto:${facility.email}`}
              className="inline-flex h-11 items-center justify-center gap-2 rounded-xl border border-border bg-background/80 text-sm font-semibold transition hover:bg-secondary"
            >
              <MailIcon className="h-5 w-5 shrink-0 text-muted-foreground" aria-hidden />
              {t("facilities.sidebarEmail")}
            </a>
          ) : null}

          {facility.website ? (
            <a
              href={facility.website}
              target="_blank"
              rel="noopener noreferrer"
              className="inline-flex h-11 items-center justify-center gap-2 rounded-xl border border-border bg-background/80 text-sm font-semibold transition hover:bg-secondary"
            >
              <GlobeIcon className="h-5 w-5 shrink-0 text-muted-foreground" aria-hidden />
              {t("directory.website")}
            </a>
          ) : null}

          {mapFallback ? (
            <a
              href={mapFallback}
              target="_blank"
              rel="noopener noreferrer"
              className="inline-flex h-11 items-center justify-center gap-2 rounded-xl border border-dashed border-border text-sm font-semibold text-muted-foreground transition hover:border-primary/30 hover:text-primary"
            >
              <PinIcon className="h-5 w-5 shrink-0" aria-hidden />
              {t("directory.viewOnMap")}
            </a>
          ) : null}
        </div>

        {[facility.address, facility.city].filter(Boolean).length > 0 ? (
          <address className="not-italic text-sm leading-relaxed text-muted-foreground">
            {facility.address ? <div>{facility.address}</div> : null}
            {facility.city ? <div>{facility.city}</div> : null}
          </address>
        ) : null}

        <div className="rounded-xl border border-primary/15 bg-primary/[0.06] p-4 text-xs leading-relaxed text-muted-foreground">
          <div className="mb-2 flex items-center gap-2 font-semibold text-foreground">
            <ShieldIcon className="h-4 w-4 shrink-0 text-primary" aria-hidden />
            {t("facilities.sidebarDisclaimerTitle")}
          </div>
          {t("footer.informational")}
        </div>
      </Card>

      <FacilityMapEmbed
        latitude={facility.latitude}
        longitude={facility.longitude}
        name={facility.name}
      />
    </aside>
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

function GlobeIcon({ className }: { className?: string }) {
  return (
    <svg className={className} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
      <circle cx="12" cy="12" r="10" />
      <path d="M2 12h20M12 2a15.3 15.3 0 014 10 15.3 15.3 0 01-4 10 15.3 15.3 0 01-4-10 15.3 15.3 0 014-10z" />
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
