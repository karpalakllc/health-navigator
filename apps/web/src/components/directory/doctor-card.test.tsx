import { render, screen } from "@testing-library/react";
import { describe, expect, it } from "vitest";
import { DoctorCard } from "@/components/directory/doctor-card";
import type { DoctorListItem } from "@/lib/api/types";

const marko: DoctorListItem = {
  slug: "marko-stojanov",
  full_name: "д-р Марко Стојанов",
  title: null,
  subspecialty: "Неонатологија",
  city: "Скопје",
  avatar_url: null,
  years_experience: null,
  accepts_new_patients: true,
  is_featured: false,
  is_sponsored: false,
  primary_specialty: { slug: "pedijatrija", name: "Педијатрија" },
  specialties: [
    { slug: "pedijatrija", name: "Педијатрија" },
    { slug: "kardiologija", name: "Кардиологија" },
  ],
  primary_facility: null,
  review_summary: { count: 0, average_rating: null },
};

describe("DoctorCard", () => {
  it("names the secondary specialties a filter or search may have matched", () => {
    render(<DoctorCard doctor={marko} />);

    expect(screen.getByText("Педијатрија · Неонатологија")).toBeInTheDocument();
    // Under the Кардиологија filter this card looked like a wrong result.
    expect(screen.getByText("Исто така: Кардиологија")).toBeInTheDocument();
  });

  it("uses the name's initials, not the д-р title, for the monogram", () => {
    render(<DoctorCard doctor={marko} />);

    expect(screen.getByText("МС")).toBeInTheDocument();
  });

  it("adds no extra line for a doctor with one specialty", () => {
    render(
      <DoctorCard
        doctor={{ ...marko, specialties: [marko.specialties![0]] }}
      />,
    );

    expect(screen.queryByText(/Исто така/)).not.toBeInTheDocument();
  });
});
