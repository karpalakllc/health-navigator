import { render, screen } from "@testing-library/react";
import { describe, expect, it } from "vitest";
import { PharmacyCard } from "@/components/directory/pharmacy-card";
import type { PharmacyListItem } from "@/lib/api/types";
import { t } from "@/i18n/t";

const pharmacy: PharmacyListItem = {
  slug: "eurofarm-skopje",
  name: "Еурофарм Скопје",
  city: "Скопје",
  avatar_url: null,
  review_summary: { count: 0, average_rating: null },
};

describe("PharmacyCard", () => {
  it("keeps the letter disc at its fixed size so the name column is not squeezed to zero", () => {
    render(<PharmacyCard pharmacy={pharmacy} />);

    expect(
      screen.getByRole("heading", { level: 2, name: "Еурофарм Скопје" }),
    ).toBeInTheDocument();

    // cn() only concatenates, so a `w-full` next to the disc's fixed size
    // used to win in the stylesheet: the shrink-0 disc took the whole row
    // and the min-w-0 name column collapsed to 0 px.
    const disc = screen.getByText("Е", { selector: "[aria-hidden]" });
    expect(disc.className.split(/\s+/)).toEqual(
      expect.arrayContaining(["size-14", "shrink-0"]),
    );
    expect(disc.className.split(/\s+/)).not.toContain("w-full");
    expect(disc.className.split(/\s+/)).not.toContain("h-full");
  });

  it("links to the profile with a name that says whose profile it is", () => {
    render(<PharmacyCard pharmacy={pharmacy} />);

    expect(
      screen.getByRole("link", { name: "Види профил: Еурофарм Скопје" }),
    ).toHaveAttribute("href", "/pharmacies/eurofarm-skopje");
  });

  it("offers tap-to-call only when there is a number", () => {
    const { rerender } = render(<PharmacyCard pharmacy={pharmacy} />);
    expect(
      screen.queryByRole("link", { name: /Јави се/ }),
    ).not.toBeInTheDocument();

    rerender(
      <PharmacyCard pharmacy={{ ...pharmacy, phone: "+389 2 310 0000" }} />,
    );
    expect(
      screen.getByRole("link", { name: "Јави се: Еурофарм Скопје" }),
    ).toHaveAttribute("href", "tel:+38923100000");
  });

  it("shows the cover photo on top, or the soft placeholder without one", () => {
    const cover = "https://media.zdravje360.mk/media/pharmacies/cover.webp";
    const { container, rerender } = render(
      <PharmacyCard pharmacy={{ ...pharmacy, cover_url: cover }} />,
    );

    const area = container.querySelector("[data-cover]")!;
    expect(area).toHaveAttribute("data-cover", "image");
    expect(area.querySelector("img")).toHaveAttribute("src", cover);

    rerender(<PharmacyCard pharmacy={pharmacy} />);
    expect(container.querySelector("[data-cover]")).toHaveAttribute(
      "data-cover",
      "placeholder",
    );
    expect(container.querySelector("[data-cover] img")).toBeNull();
  });

  it("marks a featured pharmacy with „Истакнат“", () => {
    const { rerender } = render(<PharmacyCard pharmacy={pharmacy} />);
    expect(screen.queryByText(t("ui.featured"))).not.toBeInTheDocument();

    rerender(<PharmacyCard pharmacy={{ ...pharmacy, is_featured: true }} />);
    expect(screen.getByText(t("ui.featured"))).toBeInTheDocument();
  });

  it("says when there are no reviews yet", () => {
    render(<PharmacyCard pharmacy={pharmacy} />);

    expect(screen.getByText(t("directory.noReviewsYet"))).toBeInTheDocument();
  });
});
