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

  it("says when there are no reviews yet", () => {
    render(<PharmacyCard pharmacy={pharmacy} />);

    expect(screen.getByText(t("directory.noReviewsYet"))).toBeInTheDocument();
  });
});
