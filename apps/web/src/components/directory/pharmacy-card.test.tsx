import { render, screen } from "@testing-library/react";
import { describe, expect, it } from "vitest";
import { PharmacyCard } from "@/components/directory/pharmacy-card";
import type { PharmacyListItem } from "@/lib/api/types";

const pharmacy: PharmacyListItem = {
  slug: "eurofarm-skopje",
  name: "Еурофарм Скопје",
  city: "Скопје",
  avatar_url: null,
  review_summary: { count: 0, average_rating: null },
};

describe("PharmacyCard", () => {
  it.each(["grid", "list"] as const)(
    "keeps the letter tile at its fixed size so the name column is not squeezed to zero (%s)",
    (layout) => {
      render(<PharmacyCard pharmacy={pharmacy} layout={layout} />);

      expect(
        screen.getByRole("heading", { level: 2, name: "Еурофарм Скопје" }),
      ).toBeInTheDocument();

      // cn() only concatenates, so a `w-full` from fallbackClassName sat next
      // to the tile's `w-[62px]` and won in the stylesheet: the shrink-0 tile
      // took the whole row and the min-w-0 name column collapsed to 0 px.
      const tile = screen.getByText("Е", { selector: "[aria-hidden]" });
      expect(tile.className.split(/\s+/)).toEqual(
        expect.arrayContaining(["h-[62px]", "w-[62px]", "shrink-0"]),
      );
      expect(tile.className.split(/\s+/)).not.toContain("w-full");
      expect(tile.className.split(/\s+/)).not.toContain("h-full");
    },
  );
});
