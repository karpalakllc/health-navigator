import { t } from "@/i18n/t";

export function FacilityEmergencyBanner() {
  return (
    <div
      role="note"
      className="flex gap-3 rounded-2xl border border-amber-500/30 bg-amber-500/10 px-4 py-3 text-sm leading-relaxed text-foreground"
    >
      <EmergencyIcon
        className="mt-0.5 h-5 w-5 shrink-0 text-amber-600"
        aria-hidden
      />
      <div>
        <p className="font-semibold">{t("facilities.emergencyBannerTitle")}</p>
        <p className="mt-1 text-muted-foreground">
          {t("facilities.emergencyBannerBody")}
        </p>
      </div>
    </div>
  );
}

function EmergencyIcon({ className }: { className?: string }) {
  return (
    <svg
      className={className}
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      strokeWidth="2"
    >
      <path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" />
      <path d="M12 9v4M12 17h.01" strokeLinecap="round" />
    </svg>
  );
}
