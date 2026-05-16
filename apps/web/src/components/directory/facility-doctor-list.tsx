import Link from "next/link";
import { Badge } from "@/components/ui/badge";
import type { FacilityDetail } from "@/lib/api/types";
import { t } from "@/i18n/t";

type FacilityDoctor = FacilityDetail["doctors"][number];

export function FacilityDoctorList({
  doctors,
  emptyMessage,
}: {
  doctors: FacilityDoctor[];
  emptyMessage: string;
}) {
  if (doctors.length === 0) {
    return <p className="text-sm text-muted-foreground">{emptyMessage}</p>;
  }

  return (
    <ul className="grid gap-3 sm:grid-cols-2">
      {doctors.map((doctor) => (
        <li key={doctor.slug}>
          <Link
            href={`/doctors/${doctor.slug}`}
            className="group flex gap-4 rounded-2xl border border-border bg-card p-4 shadow-sm transition hover:border-primary/25 hover:shadow-[0_16px_40px_-28px_rgb(15_23_42/0.4)]"
          >
            <div className="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-primary/12 to-accent/12 text-sm font-bold text-primary ring-1 ring-primary/10">
              {doctor.full_name.charAt(0)}
            </div>
            <div className="min-w-0 flex-1">
              <p className="font-semibold text-foreground group-hover:text-primary">
                {doctor.full_name}
              </p>
              {doctor.title ? (
                <p className="mt-0.5 text-sm text-muted-foreground">{doctor.title}</p>
              ) : null}
              {doctor.is_primary ? (
                <Badge variant="accent" className="mt-2">
                  {t("facilities.primaryWorkplace")}
                </Badge>
              ) : null}
            </div>
            <span className="self-center text-muted-foreground opacity-0 transition group-hover:opacity-100">
              →
            </span>
          </Link>
        </li>
      ))}
    </ul>
  );
}
