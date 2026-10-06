import Link from "next/link";
import { Icon } from "@/components/ui/icons";
import { SectionHeader } from "@/components/ui/section-header";
import type { HomeSpecialty } from "@/lib/api/home";
import { cn } from "@/lib/cn";
import { fitToColumns } from "@/lib/grid-fit";
import { specialtyIcon } from "@/lib/specialty-icons";
import { t, tCount } from "@/i18n/t";

/** Phones show at most six, wider layouts at most eight. */
const MOBILE_MAX = 6;
const WIDE_MAX = 8;

/**
 * „Популарни специјалности“: the specialties with the most published doctors
 * (GET /home/highlights), each a card linking to the doctor directory
 * filtered to it. Two columns on phones, four on desktop.
 */
export function HomeSpecialties({
  specialties,
  className,
}: {
  specialties: HomeSpecialty[];
  className?: string;
}) {
  if (specialties.length === 0) {
    return null;
  }

  // Whole rows only, so no card is left alone on the last row: 2 columns on
  // phones, 3 from sm, 4 from lg.
  const total = specialties.length;
  const phone = fitToColumns(total, 2, MOBILE_MAX);
  const tablet = fitToColumns(total, 3, WIDE_MAX);
  const desktop = fitToColumns(total, 4, WIDE_MAX);

  return (
    <section aria-labelledby="home-specialties-title" className={className}>
      <SectionHeader
        id="home-specialties-title"
        title={t("homeSections.specialtiesTitle")}
        action={{ href: "/doctors", label: t("home.topRatedViewAll") }}
        className="items-center"
      />
      <ul className="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:mt-6 lg:grid-cols-4 lg:gap-6">
        {specialties.map((specialty, index) => (
          <li
            key={specialty.slug}
            className={cn(
              "flex",
              index >= phone && "max-sm:hidden",
              index >= tablet && "sm:max-lg:hidden",
              index >= desktop && "lg:hidden",
            )}
          >
            <Link
              href={`/doctors?specialty=${encodeURIComponent(specialty.slug)}`}
              className="card hover-lift flex min-h-[72px] w-full flex-col items-start gap-3 p-3 text-ink sm:p-4 lg:flex-row lg:items-center lg:gap-4"
            >
              <span className="flex size-11 flex-none items-center justify-center rounded-full bg-chip-tint lg:size-12">
                <Icon name={specialtyIcon(specialty)} size={22} />
              </span>
              <span className="w-full min-w-0 lg:w-auto lg:flex-1">
                <span className="block break-words font-ui text-[0.9375rem] font-semibold leading-5 sm:text-base lg:text-[1.0625rem] lg:leading-6">
                  {specialty.name}
                </span>{" "}
                <span className="mt-0.5 block text-[0.9375rem] leading-5 text-ink-2">
                  {tCount(
                    "homeSections.specialtyDoctors",
                    specialty.doctors_count,
                  )}
                </span>
              </span>
            </Link>
          </li>
        ))}
      </ul>
    </section>
  );
}
