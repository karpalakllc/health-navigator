import Link from "next/link";
import { DoctorCard } from "@/components/directory/doctor-card";
import type { DoctorListItem } from "@/lib/api/types";

export function HomeDoctorsRail({
  doctors,
  title,
  description,
  viewAllHref,
  viewAllLabel,
  disclaimer,
}: {
  doctors: DoctorListItem[];
  title: string;
  description: string;
  viewAllHref: string;
  viewAllLabel: string;
  disclaimer?: string;
}) {
  if (doctors.length === 0) {
    return null;
  }

  return (
    <section className="space-y-4">
      <div className="flex flex-wrap items-end justify-between gap-3">
        <div className="flex items-start gap-3">
          <span className="mt-1 flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-amber-400/15 text-amber-700">
            <StarBurstGlyph className="h-5 w-5" aria-hidden />
          </span>
          <div>
            <h2 className="text-xl font-semibold tracking-tight">{title}</h2>
            <p className="mt-1 max-w-xl text-sm text-muted-foreground">{description}</p>
          </div>
        </div>
        <Link href={viewAllHref} className="shrink-0 text-sm font-semibold text-primary hover:underline">
          {viewAllLabel} →
        </Link>
      </div>

      {disclaimer ? <p className="text-xs text-muted-foreground">{disclaimer}</p> : null}

      <div className="relative -mx-4 sm:mx-0">
        <div className="flex gap-4 overflow-x-auto px-4 pb-1 pt-1 [scrollbar-width:none] sm:px-0 [&::-webkit-scrollbar]:hidden snap-x snap-mandatory">
          {doctors.map((doctor) => (
            <div key={doctor.slug} className="min-w-[min(320px,calc(100vw-4rem))] max-w-[340px] shrink-0 snap-start">
              <DoctorCard doctor={doctor} />
            </div>
          ))}
        </div>
      </div>
    </section>
  );
}

function StarBurstGlyph({ className }: { className?: string }) {
  return (
    <svg className={className} viewBox="0 0 24 24" fill="currentColor" aria-hidden>
      <path d="M12 2l1.8 5.6h5.9l-4.8 3.5 1.8 5.6L12 14.9 7.3 16.7l1.8-5.6L4.3 7.6h5.9z" opacity="0.9" />
    </svg>
  );
}
