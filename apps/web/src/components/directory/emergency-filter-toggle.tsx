import { cn } from "@/lib/cn";
import { t } from "@/i18n/t";

export function EmergencyFilterToggle({
  defaultChecked = false,
  className,
}: {
  defaultChecked?: boolean;
  className?: string;
}) {
  return (
    <label
      className={cn(
        "group inline-flex min-h-[42px] cursor-pointer items-center gap-3 rounded-full border border-border bg-white py-2 pl-2.5 pr-4 transition",
        "has-[:checked]:border-amber-500/35 has-[:checked]:bg-amber-500/[0.08]",
        className,
      )}
    >
      <input
        type="checkbox"
        name="has_emergency"
        value="1"
        defaultChecked={defaultChecked}
        className="sr-only"
      />
      <span
        className="relative inline-flex h-7 w-12 shrink-0 rounded-full bg-[#e2e8ec] p-0.5 transition group-has-[:checked]:bg-amber-500"
        aria-hidden
      >
        <span className="block size-6 rounded-full bg-white shadow-[0_2px_6px_rgb(16_30_36_/_0.12)] transition-transform group-has-[:checked]:translate-x-5" />
      </span>
      <span className="flex items-center gap-2 text-sm font-bold text-[#3f4b55]">
        <EmergencyIcon
          className="h-4 w-4 shrink-0 text-amber-600"
          aria-hidden
        />
        {t("facilities.emergencyFilter")}
      </span>
    </label>
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
      <path
        d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"
        strokeLinecap="round"
      />
      <path d="M12 9v4M12 17h.01" strokeLinecap="round" />
    </svg>
  );
}
