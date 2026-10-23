import { render, screen } from "@testing-library/react";
import { describe, expect, it, vi } from "vitest";
import type { GuidanceCatalog } from "@/lib/api/guidance-v2";

const catalog: GuidanceCatalog = {
  max_symptoms: 3,
  // Local draft preview on: the flows show, but no draft pill does.
  preview: true,
  flows: [
    {
      key: "headache",
      title: "Главоболка",
      body_areas: ["head"],
      search_terms: ["глава"],
      age_bands: ["adult_18_64"],
      urgency_rank: 50,
    },
  ],
};

vi.mock("@/lib/api/settings", () => ({
  fetchPublicSettings: async () => ({ public_guidance: true }),
}));
vi.mock("@/lib/api/guidance-flow", () => ({
  fetchGuidanceCatalog: async () => catalog,
}));

import GuidancePage from "@/app/guidance/page";
import { t } from "@/i18n/t";

describe("/guidance", () => {
  it("renders the calm hero without the urgent-help button, the 194/112 line, a disclaimer or a draft pill", async () => {
    render(await GuidancePage());

    expect(
      screen.getByRole("heading", { level: 1, name: t("guidance.title") }),
    ).toBeInTheDocument();
    expect(
      screen.getByText(
        "Општи информации што ќе ви помогнат да одлучите за следниот чекор.",
      ),
    ).toBeInTheDocument();
    expect(screen.getByText(t("guidance.introAnonymous"))).toBeInTheDocument();

    expect(
      screen.queryByRole("button", { name: t("guidance.emergencyNow") }),
    ).toBeNull();
    expect(screen.queryByText(/Итно\? Повикајте/)).toBeNull();
    expect(screen.queryByRole("link", { name: "194" })).toBeNull();
    expect(screen.queryByText(/Не е медицински совет/)).toBeNull();
    expect(screen.queryByText(/Преглед на нацрти/)).toBeNull();
  });
});
