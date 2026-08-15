import Link from "next/link";
import { cn } from "@/lib/cn";
import type { Specialty } from "@/lib/api/types";
import { tFormat } from "@/i18n/t";

const specialtyIcons = [
  {
    slugIncludes: ["kardi", "cardio", "срце"],
    icon: HeartIcon,
    tone: "icon-soft-red",
  },
  { slugIncludes: ["pediat", "детск"], icon: BabyIcon, tone: "icon-soft-teal" },
  { slugIncludes: ["dermat", "кож"], icon: FaceIcon, tone: "icon-soft-red" },
  {
    slugIncludes: ["ortop", "ortho", "кост"],
    icon: BoneIcon,
    tone: "icon-soft-teal",
  },
  {
    slugIncludes: ["ginek", "gyne", "акуш"],
    icon: VenusIcon,
    tone: "icon-soft-red",
  },
] as const;

function iconForSpecialty(slug: string, index: number) {
  const lower = slug.toLowerCase();
  const match = specialtyIcons.find((entry) =>
    entry.slugIncludes.some((part) => lower.includes(part)),
  );
  if (match) {
    return match;
  }
  return index % 2 === 0
    ? { icon: HeartIcon, tone: "icon-soft-red" as const }
    : { icon: BoneIcon, tone: "icon-soft-teal" as const };
}

export function HomeSpecialtyExplorer({
  specialties,
}: {
  specialties: Specialty[];
}) {
  const items = [...specialties]
    .filter((s) => s.doctors_count > 0)
    .sort((a, b) => b.doctors_count - a.doctors_count)
    .slice(0, 5);

  if (items.length === 0) {
    return null;
  }

  return (
    <ul className="grid gap-4 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-5">
      {items.map((s, index) => {
        const { icon: Icon, tone } = iconForSpecialty(s.slug, index);
        return (
          <li key={s.slug}>
            <Link
              href={`/doctors?specialty=${encodeURIComponent(s.slug)}`}
              className="block h-full"
            >
              <article className="surface-glass card-lift h-full rounded-3xl p-6">
                <span
                  className={cn(
                    "mb-4 inline-flex h-[54px] w-[54px] items-center justify-center rounded-[1.125rem]",
                    tone,
                  )}
                >
                  <Icon />
                </span>
                <h3 className="font-extrabold text-foreground">{s.name}</h3>
                <p className="mt-2 text-sm text-muted-foreground">
                  {tFormat("home.specialtyDoctorCount", {
                    count: String(s.doctors_count),
                  })}
                </p>
              </article>
            </Link>
          </li>
        );
      })}
    </ul>
  );
}

function HeartIcon() {
  return (
    <svg
      className="h-6 w-6"
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      strokeWidth="1.75"
      aria-hidden
    >
      <path
        d="M19.5 12.572l-7.5 7.428-7.5-7.428a5 5 0 117.5-6.066 5 5 0 117.5 6.066z"
        strokeLinecap="round"
        strokeLinejoin="round"
      />
    </svg>
  );
}

function BabyIcon() {
  return (
    <svg
      className="h-6 w-6"
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      strokeWidth="1.75"
      aria-hidden
    >
      <path
        d="M9 12h.01M15 12h.01M10 16c.667.667 1.333 1 2 1s1.333-.333 2-1M12 22c5.523 0 10-4.477 10-10S17.523 2 12 2 2 6.477 2 12s4.477 10 10 10z"
        strokeLinecap="round"
        strokeLinejoin="round"
      />
    </svg>
  );
}

function FaceIcon() {
  return (
    <svg
      className="h-6 w-6"
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      strokeWidth="1.75"
      aria-hidden
    >
      <path
        d="M12 11a3 3 0 100-6 3 3 0 000 6zM7 20v-1a5 5 0 0110 0v1"
        strokeLinecap="round"
        strokeLinejoin="round"
      />
    </svg>
  );
}

function BoneIcon() {
  return (
    <svg
      className="h-6 w-6"
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      strokeWidth="1.75"
      aria-hidden
    >
      <path
        d="M15 9l-6 6M9.5 8.5l1 1M13.5 12.5l1 1M8 14l-1.5 1.5a2.12 2.12 0 103 3L16 10l1.5-1.5a2.12 2.12 0 10-3-3L13 8"
        strokeLinecap="round"
        strokeLinejoin="round"
      />
    </svg>
  );
}

function VenusIcon() {
  return (
    <svg
      className="h-6 w-6"
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      strokeWidth="1.75"
      aria-hidden
    >
      <circle cx="12" cy="9" r="4" />
      <path d="M12 13v8M9 18h6" strokeLinecap="round" />
    </svg>
  );
}
