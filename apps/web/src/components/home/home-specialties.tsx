import Link from "next/link";
import { Icon } from "@/components/ui/icons";
import { SectionHeader } from "@/components/ui/section-header";
import type { HomeSpecialty } from "@/lib/api/home";
import { cn } from "@/lib/cn";
import { specialtyIcon } from "@/lib/specialty-icons";
import { t, tCount } from "@/i18n/t";

/** Shown on phones; the rest (up to 8) join from the sm breakpoint. */
const MOBILE_COUNT = 6;

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

  return (
    <section aria-labelledby="home-specialties-title" className={className}>
      <SectionHeader
        id="home-specialties-title"
        title={t("homeSections.specialtiesTitle")}
        action={{ href: "/doctors", label: t("homeSections.specialtiesAll") }}
        className="items-center"
      />
      <ul className="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:mt-6 lg:grid-cols-4 lg:gap-6">
        {specialties.map((specialty, index) => (
          <li
            key={specialty.slug}
            className={cn("flex", index >= MOBILE_COUNT && "max-sm:hidden")}
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
              <Icon
                name="chevron-right"
                size={20}
                className="icon-nudge hidden text-ink-2 lg:block"
              />
            </Link>
          </li>
        ))}
      </ul>
    </section>
  );
}
