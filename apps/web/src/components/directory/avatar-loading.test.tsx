import { render } from "@testing-library/react";
import { describe, expect, it } from "vitest";
import type { ReactElement } from "react";
import { DirectoryAvatar } from "@/components/directory/directory-avatar";
import { DoctorCard } from "@/components/directory/doctor-card";
import { FacilityCard } from "@/components/directory/facility-card";
import { HomeFacilityCard } from "@/components/directory/home-facility-card";
import type { DoctorListItem, FacilityListItem } from "@/lib/api/types";

/**
 * Directory listings render dozens of uploaded avatars as raw <img> (next/image
 * cannot be pointed at the API host). Without these attributes every one of them
 * was fetched and decoded on the main thread up front, including those far
 * below the fold.
 */
const AVATAR = "https://media.zdravje360.mk/media/avatar.webp";

const doctor: DoctorListItem = {
  slug: "ana-petrovska",
  full_name: "Ана Петровска",
  title: null,
  subspecialty: null,
  city: "Скопје",
  avatar_url: AVATAR,
  years_experience: null,
  accepts_new_patients: true,
  is_featured: false,
  is_sponsored: false,
  primary_specialty: null,
  primary_facility: null,
  review_summary: { count: 0, average_rating: null },
};

const facility: FacilityListItem = {
  slug: "klinika",
  name: "Клиника",
  type: "clinic",
  city: "Скопје",
  avatar_url: AVATAR,
  has_emergency_services: false,
  is_featured: false,
  departments_count: 0,
  review_summary: { count: 0, average_rating: null },
};

const cases: [string, ReactElement][] = [
  ["doctor card", <DoctorCard key="d" doctor={doctor} />],
  ["facility card", <FacilityCard key="f" facility={facility} />],
  ["home facility card", <HomeFacilityCard key="h" facility={facility} />],
  [
    "directory avatar",
    <DirectoryAvatar key="a" kind="doctor" avatarUrl={AVATAR} name="Ана" />,
  ],
];

describe.each(cases)("%s", (_name, element) => {
  it("loads its avatar lazily and decodes it off the main thread", () => {
    const { container } = render(element);
    const img = container.querySelector(`img[src="${AVATAR}"]`);

    expect(img).not.toBeNull();
    expect(img).toHaveAttribute("loading", "lazy");
    expect(img).toHaveAttribute("decoding", "async");
  });
});

describe("DirectoryAvatar above the fold", () => {
  it("can opt out of lazy loading", () => {
    const { container } = render(
      <DirectoryAvatar
        kind="doctor"
        avatarUrl={AVATAR}
        name="Ана"
        loading="eager"
      />,
    );

    expect(container.querySelector("img")).toHaveAttribute("loading", "eager");
  });
});
