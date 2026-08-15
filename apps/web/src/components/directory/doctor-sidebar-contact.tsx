import Link from "next/link";
import type { DoctorDetail } from "@/lib/api/types";
import { facilityPublicPath } from "@/lib/facility-labels";
import { t } from "@/i18n/t";

export function DoctorSidebarContact({ doctor }: { doctor: DoctorDetail }) {
  const primaryFacility =
    doctor.facilities.find((f) => f.is_primary) ?? doctor.facilities[0];
  const telHref = doctor.phone
    ? `tel:${doctor.phone.replace(/\s+/g, "")}`
    : null;

  return (
    <aside className="content-card rounded-[1.875rem] p-[22px] lg:sticky lg:top-28 lg:self-start">
      <h2 className="text-[1.7rem] font-black tracking-tight text-foreground">
        {t("doctors.quickActionsTitle")}
      </h2>

      <div className="mt-[18px] flex flex-col">
        {telHref ? (
          <a
            href={telHref}
            className="btn-gradient-primary inline-flex min-h-[58px] w-full items-center justify-center gap-2.5 rounded-[1.25rem] text-sm font-extrabold text-white transition hover:brightness-105"
          >
            <PhoneIcon className="h-5 w-5 shrink-0" aria-hidden />
            {t("doctors.sidebarCall")}
          </a>
        ) : (
          <p className="rounded-full border border-border bg-[#f7f9fa] px-4 py-3 text-sm text-muted-foreground">
            {t("doctors.sidebarNoPhone")}
          </p>
        )}

        {doctor.email ? (
          <a
            href={`mailto:${doctor.email}`}
            className="mt-3 inline-flex min-h-[54px] w-full items-center justify-center gap-2.5 rounded-full border border-border bg-white text-sm font-bold text-[#495661] transition hover:bg-[#f7f9fa]"
          >
            <MailIcon className="h-5 w-5 shrink-0" aria-hidden />
            {t("doctors.sidebarEmail")}
          </a>
        ) : null}

        {primaryFacility ? (
          <Link
            href={facilityPublicPath(
              primaryFacility.type,
              primaryFacility.slug,
            )}
            className="mt-3 inline-flex min-h-[54px] w-full items-center justify-center gap-2.5 rounded-full border border-border bg-white text-sm font-bold text-[#495661] transition hover:bg-[#f7f9fa]"
          >
            <PinIcon className="h-5 w-5 shrink-0" aria-hidden />
            {t("doctors.sidebarPrimaryFacility")}
          </Link>
        ) : (
          <Link
            href="#doctor-locations"
            className="mt-3 inline-flex min-h-[54px] w-full items-center justify-center gap-2.5 rounded-full border border-border bg-white text-sm font-bold text-[#495661] transition hover:bg-[#f7f9fa]"
          >
            <PinIcon className="h-5 w-5 shrink-0" aria-hidden />
            {t("doctors.sidebarLocations")}
          </Link>
        )}
      </div>

      {doctor.consultation_fee_note ? (
        <p className="mt-[18px] text-base text-[#59656f]">
          <span>{t("doctors.consultationFee")}: </span>
          <strong className="text-[#39454f]">
            {doctor.consultation_fee_note}
          </strong>
        </p>
      ) : null}

      <div className="mt-[18px] rounded-[1.375rem] border border-primary/15 bg-[#fff1f1] p-4">
        <div className="mb-2.5 flex items-center gap-2.5 font-extrabold text-[#313d47]">
          <ShieldIcon className="h-5 w-5 shrink-0 text-primary" aria-hidden />
          {t("doctors.sidebarDisclaimerTitle")}
        </div>
        <p className="text-sm leading-relaxed text-[#63707b]">
          {t("footer.informational")}
        </p>
      </div>
    </aside>
  );
}

function PhoneIcon({ className }: { className?: string }) {
  return (
    <svg
      className={className}
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      strokeWidth="2"
    >
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
    <svg
      className={className}
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      strokeWidth="2"
    >
      <path
        d="M4 4h16a2 2 0 012 2v12a2 2 0 01-2 2H4a2 2 0 01-2-2V6a2 2 0 012-2z"
        strokeLinecap="round"
        strokeLinejoin="round"
      />
      <path d="m22 6-10 7L2 6" strokeLinecap="round" strokeLinejoin="round" />
    </svg>
  );
}

function PinIcon({ className }: { className?: string }) {
  return (
    <svg
      className={className}
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      strokeWidth="2"
    >
      <path
        d="M12 21s7-4.35 7-11a7 7 0 10-14 0c0 6.65 7 11 7 11z"
        strokeLinecap="round"
        strokeLinejoin="round"
      />
      <circle cx="12" cy="10" r="2.5" />
    </svg>
  );
}

function ShieldIcon({ className }: { className?: string }) {
  return (
    <svg
      className={className}
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      strokeWidth="2"
    >
      <path
        d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"
        strokeLinecap="round"
        strokeLinejoin="round"
      />
    </svg>
  );
}
