import { fireEvent, render, screen } from "@testing-library/react";
import { describe, expect, it } from "vitest";
import { FacilityCard } from "@/components/directory/facility-card";
import type { FacilityListItem } from "@/lib/api/types";
import { t } from "@/i18n/t";

const COVER = "https://media.zdravje360.mk/media/facilities/cover.webp";
const LOGO = "https://media.zdravje360.mk/media/facilities/logo.webp";

const facility: FacilityListItem = {
  slug: "klinika-sistina",
  name: "Клиничка болница Систина",
  type: "hospital",
  city: "Скопје",
  avatar_url: null,
  cover_url: null,
  has_emergency_services: true,
  is_featured: false,
  departments_count: 12,
  review_summary: { count: 4, average_rating: 4.5 },
};

function cover(container: HTMLElement) {
  return container.querySelector<HTMLElement>("[data-cover]")!;
}

describe("FacilityCard cover", () => {
  it("shows the cover photo across the top, decorative and lazy", () => {
    const { container } = render(
      <FacilityCard facility={{ ...facility, cover_url: COVER }} />,
    );

    const area = cover(container);
    expect(area).toHaveAttribute("data-cover", "image");
    // The cover sits above the heading, and keeps a fixed aspect.
    expect(area.className).toMatch(/aspect-video/);
    expect(area.className).toMatch(/md:aspect-\[2\/1\]/);
    const img = area.querySelector("img")!;
    expect(img).toHaveAttribute("src", COVER);
    expect(img).toHaveAttribute("alt", "");
    expect(img).toHaveAttribute("loading", "lazy");
    expect(img).toHaveAttribute("decoding", "async");
    expect(
      area.compareDocumentPosition(
        screen.getByRole("heading", { name: facility.name }),
      ) & Node.DOCUMENT_POSITION_FOLLOWING,
    ).toBeTruthy();
  });

  it("shows the soft placeholder when there is no cover", () => {
    const { container } = render(<FacilityCard facility={facility} />);

    const area = cover(container);
    expect(area).toHaveAttribute("data-cover", "placeholder");
    expect(area.querySelector("img")).toBeNull();
    expect(area.querySelector("svg")).not.toBeNull();
  });

  it("swaps a cover that fails to load for the placeholder", () => {
    const { container } = render(
      <FacilityCard facility={{ ...facility, cover_url: COVER }} />,
    );

    fireEvent.error(cover(container).querySelector("img")!);

    expect(cover(container)).toHaveAttribute("data-cover", "placeholder");
  });

  it("overlays the logo when there is one, else the initial", () => {
    const { container, rerender } = render(
      <FacilityCard facility={{ ...facility, avatar_url: LOGO }} />,
    );
    const slot = container.querySelector("[data-cover-logo]")!;
    expect(slot.querySelector(`img[src="${LOGO}"]`)).not.toBeNull();

    rerender(<FacilityCard facility={facility} />);
    expect(
      container.querySelector("[data-cover-logo]")!.querySelector("img"),
    ).toBeNull();
    expect(
      screen.getByText("К", { selector: "[aria-hidden]" }),
    ).toBeInTheDocument();
  });
});

describe("FacilityCard content", () => {
  it("keeps the kind, place, rating, tags and the profile action", () => {
    render(<FacilityCard facility={facility} />);

    expect(
      screen.getByRole("heading", { level: 2, name: facility.name }),
    ).toBeInTheDocument();
    expect(screen.getByText("Скопје")).toBeInTheDocument();
    expect(screen.getByText("4,5")).toBeInTheDocument();
    expect(
      screen.getByText(t("facilities.emergencyAvailable")),
    ).toBeInTheDocument();
    expect(
      screen.getByRole("link", { name: `Види профил: ${facility.name}` }),
    ).toHaveAttribute("href", "/facilities/klinika-sistina");
  });

  it("marks a featured facility with „Истакнат“ on the cover", () => {
    const { container, rerender } = render(
      <FacilityCard facility={facility} />,
    );
    expect(screen.queryByText(t("ui.featured"))).not.toBeInTheDocument();

    rerender(<FacilityCard facility={{ ...facility, is_featured: true }} />);
    const label = screen.getByText(t("ui.featured"));
    expect(cover(container).parentElement).toContainElement(label);
  });
});
