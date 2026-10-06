import { fireEvent, render, screen } from "@testing-library/react";
import { describe, expect, it } from "vitest";
import { DoctorCard } from "@/components/directory/doctor-card";
import type { DoctorListItem } from "@/lib/api/types";
import { t } from "@/i18n/t";

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

  it("shows the place, the rating with one decimal and the review count", () => {
    render(
      <DoctorCard
        doctor={{
          ...marko,
          primary_facility: { name: "Клиника Ана", city: "Скопје" },
          review_summary: { count: 23, average_rating: 4.75 },
        }}
      />,
    );

    expect(screen.getByText("Клиника Ана, Скопје")).toBeInTheDocument();
    expect(screen.getByText("4,8")).toBeInTheDocument();
    expect(screen.getByText("23 рецензии")).toBeInTheDocument();
  });

  it("writes a whole-number average as „5,0“, like every other rating", () => {
    render(
      <DoctorCard
        doctor={{ ...marko, review_summary: { count: 2, average_rating: 5 } }}
      />,
    );

    expect(screen.getByText("5,0")).toBeInTheDocument();
  });

  it("says so when there are no reviews instead of showing zero stars", () => {
    render(<DoctorCard doctor={marko} />);

    expect(screen.getByText("Сè уште нема рецензии")).toBeInTheDocument();
    expect(screen.queryByRole("img")).not.toBeInTheDocument();
  });

  it("calls the doctor's number when the list sends one", () => {
    render(<DoctorCard doctor={{ ...marko, phone: "02 312 4567" }} />);

    expect(
      screen.getByRole("link", { name: "Јави се: д-р Марко Стојанов" }),
    ).toHaveAttribute("href", "tel:023124567");
    expect(
      screen.getByRole("link", { name: "Види профил: д-р Марко Стојанов" }),
    ).toHaveAttribute("href", "/doctors/marko-stojanov");
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

describe("DoctorCard photo", () => {
  const photo = "https://media.zdravje360.mk/media/doctors/marko.webp";

  it("shows the API photo named after the doctor, at a fixed size", () => {
    render(<DoctorCard doctor={{ ...marko, avatar_url: photo }} />);

    const img = screen.getByRole("img", { name: "д-р Марко Стојанов" });
    expect(img).toHaveAttribute("src", photo);
    expect(img).toHaveAttribute("width", "56");
    expect(img).toHaveAttribute("height", "56");
  });

  it("falls back to the monogram when the photo fails to load", () => {
    render(<DoctorCard doctor={{ ...marko, avatar_url: photo }} />);

    fireEvent.error(screen.getByRole("img", { name: "д-р Марко Стојанов" }));

    expect(
      screen.queryByRole("img", { name: "д-р Марко Стојанов" }),
    ).not.toBeInTheDocument();
    expect(screen.getByText("МС")).toBeInTheDocument();
  });
});

describe("DoctorCard featured variant", () => {
  it("puts „Истакнат“ in an apricot band at the top and enlarges the photo", () => {
    const { container } = render(
      <DoctorCard doctor={{ ...marko, is_featured: true }} />,
    );

    const card = container.querySelector("article")!;
    expect(card).toHaveAttribute("data-featured", "true");
    const band = card.firstElementChild!;
    expect(band.className.split(/\s+/)).toContain("bg-apricot");
    expect(band).toHaveTextContent(t("ui.featured"));
    // The band comes before the name: the label is read first.
    expect(
      band.compareDocumentPosition(
        screen.getByRole("heading", { name: "д-р Марко Стојанов" }),
      ) & Node.DOCUMENT_POSITION_FOLLOWING,
    ).toBeTruthy();
    expect(screen.getByText("МС").className.split(/\s+/)).toContain("size-16");
  });

  it("keeps „Спонзорирано“ beside „Истакнат“ on a paid placement", () => {
    render(
      <DoctorCard
        doctor={{ ...marko, is_featured: true, is_sponsored: true }}
      />,
    );

    expect(screen.getByText(t("ui.featured"))).toBeInTheDocument();
    expect(screen.getByText(t("doctors.sponsored"))).toBeInTheDocument();
  });

  it("labels a sponsored, non-featured doctor without the band", () => {
    const { container } = render(
      <DoctorCard doctor={{ ...marko, is_sponsored: true }} />,
    );

    expect(screen.getByText(t("doctors.sponsored"))).toBeInTheDocument();
    expect(screen.queryByText(t("ui.featured"))).not.toBeInTheDocument();
    expect(container.querySelector("article")).not.toHaveAttribute(
      "data-featured",
    );
  });

  it("leaves an ordinary result without either label", () => {
    render(<DoctorCard doctor={marko} />);

    expect(screen.queryByText(t("ui.featured"))).not.toBeInTheDocument();
    expect(screen.queryByText(t("doctors.sponsored"))).not.toBeInTheDocument();
    expect(screen.getByText("МС").className.split(/\s+/)).toContain("size-14");
  });
});
