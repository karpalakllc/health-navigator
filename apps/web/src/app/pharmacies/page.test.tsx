import { render, screen } from "@testing-library/react";
import { beforeEach, describe, expect, it, vi } from "vitest";
import type { PharmacyListItem } from "@/lib/api/types";

const fetchPharmacies = vi.fn();

vi.mock("@/lib/api/settings", () => ({
  fetchPublicSettings: async () => ({ public_pharmacies: true }),
}));
vi.mock("@/lib/api/pharmacies", () => ({
  fetchPharmacies: (...args: unknown[]) => fetchPharmacies(...args),
}));

import PharmaciesPage from "@/app/pharmacies/page";
import { t } from "@/i18n/t";
import { seriousA11yViolations } from "../../../test/axe";

function item(
  slug: string,
  duty: PharmacyListItem["on_duty_today"] = null,
): PharmacyListItem {
  return {
    slug,
    name: `Аптека ${slug}`,
    city: "Битола",
    avatar_url: null,
    review_summary: { count: 0, average_rating: null },
    on_duty_today: duty,
  };
}

function page(data: PharmacyListItem[]) {
  fetchPharmacies.mockResolvedValue({
    data,
    meta: {
      current_page: 1,
      last_page: 1,
      per_page: 15,
      total: data.length,
    },
  });
}

async function renderPage(params: Record<string, string>) {
  render(await PharmaciesPage({ searchParams: Promise.resolve(params) }));
}

describe("/pharmacies on duty", () => {
  beforeEach(() => {
    fetchPharmacies.mockReset();
  });

  it("passes on_duty=1 to the API only when the toggle is on", async () => {
    page([]);
    await renderPage({ city: "Битола", on_duty: "1" });
    expect(fetchPharmacies).toHaveBeenCalledWith(
      expect.objectContaining({ city: "Битола", on_duty: "1" }),
    );

    page([]);
    await renderPage({ on_duty: "yes" });
    expect(fetchPharmacies).toHaveBeenLastCalledWith(
      expect.objectContaining({ on_duty: undefined }),
    );
  });

  it("says when no pharmacy in the city is on duty today, and links to the city's list", async () => {
    page([]);
    await renderPage({ city: "Битола", on_duty: "1" });

    expect(
      screen.getByText(
        "Нема дежурна аптека денес во Битола според распоредот на ФЗОМ.",
      ),
    ).toBeInTheDocument();
    expect(
      screen.getByRole("link", { name: "Сите аптеки во Битола" }),
    ).toHaveAttribute(
      "href",
      `/pharmacies?${new URLSearchParams({ city: "Битола" })}`,
    );
    expect(await seriousA11yViolations(document.body)).toEqual([]);
  });

  it("states ФЗОМ's caveat once above the on-duty cards", async () => {
    page([
      item("vita", { date: "2026-10-07", mode: "all_day", hours_text: null }),
      item("bela", { date: "2026-10-07", mode: "on_call", hours_text: null }),
      item("arnika"),
    ]);
    await renderPage({ city: "Битола" });

    expect(screen.getAllByText(t("urgentCare.pharmaciesSource"))).toHaveLength(
      1,
    );
    expect(screen.getByText("Дежурна денес · 24 часа")).toBeInTheDocument();
    expect(screen.getByText("Дежурна денес · по повик")).toBeInTheDocument();
  });

  it("has no caveat when nothing listed is on duty", async () => {
    page([item("arnika")]);
    await renderPage({ city: "Битола" });

    expect(
      screen.queryByText(t("urgentCare.pharmaciesSource")),
    ).not.toBeInTheDocument();
  });
});
