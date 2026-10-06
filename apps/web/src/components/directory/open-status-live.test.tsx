import { render, screen } from "@testing-library/react";
import { renderToString } from "react-dom/server";
import { describe, expect, it } from "vitest";
import { PharmacyCard } from "@/components/directory/pharmacy-card";
import type { PharmacyListItem } from "@/lib/api/types";
import { t } from "@/i18n/t";

const pharmacy: PharmacyListItem = {
  slug: "zegin-centar",
  name: "Зегин Центар",
  city: "Скопје",
  avatar_url: null,
  office_hours: { "Пон–Нед": "00:00–24:00" },
  review_summary: { count: 0, average_rating: null },
};

describe("LiveOpenStatusLine in the list cards", () => {
  // The cards are client components: server-rendered and then hydrated. An
  // „Отворено“ computed from the server clock can disagree with the browser's
  // clock at hydration, so the status is left out of the server HTML.
  it("leaves the clock-dependent status out of the server HTML", () => {
    const html = renderToString(<PharmacyCard pharmacy={pharmacy} />);

    expect(html).toContain("Зегин Центар");
    expect(html).not.toContain(t("directory.open24"));
  });

  it("shows the status once mounted in the browser", () => {
    render(<PharmacyCard pharmacy={pharmacy} />);

    expect(screen.getByText(t("directory.open24"))).toBeInTheDocument();
  });
});
