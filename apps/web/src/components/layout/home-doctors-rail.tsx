import { SectionHeading } from "@/components/design/section-heading";
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
    <section>
      <SectionHeading
        title={title}
        description={description}
        href={viewAllHref}
        linkLabel={viewAllLabel}
        icon={
          <svg
            className="h-4 w-4 text-primary"
            viewBox="0 0 24 24"
            fill="currentColor"
            aria-hidden
          >
            <path d="M12 2l2.4 7.4h7.6l-6 4.6 2.3 7.4L12 17l-6.3 4.4 2.3-7.4-6-4.6h7.6z" />
          </svg>
        }
      />
      {disclaimer ? (
        <p className="-mt-4 mb-5 text-sm text-muted-foreground">{disclaimer}</p>
      ) : null}
      <ul className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        {doctors.map((doctor) => (
          <li key={doctor.slug}>
            <DoctorCard doctor={doctor} />
          </li>
        ))}
      </ul>
    </section>
  );
}
